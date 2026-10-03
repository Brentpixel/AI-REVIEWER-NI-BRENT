<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;

/**
 * GeminiService
 *
 * Handles all communication with the Google Gemini API.
 * The API key is read from .env (GEMINI_API_KEY) and never
 * exposed to views or client-side JavaScript.
 */
class GeminiService
{
    private string $apiKey;
    private string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');

        if (empty($this->apiKey) || $this->apiKey === 'your_gemini_api_key_here') {
            throw new \RuntimeException('Gemini API key is not configured. Please set GEMINI_API_KEY in your .env file.');
        }
    }

    /**
     * Generate a Q&A reviewer from extracted document text.
     *
     * @param string $text          Extracted document text
     * @param int    $numQuestions  Number of questions to generate
     * @param string $difficulty    easy | medium | hard
     * @param string $questionType  qa | multiple_choice | identification | mixed
     * @param string $answerLength  short | detailed
     *
     * @return array  Parsed array of question objects
     * @throws \RuntimeException on API or parse failure
     */
    public function generateReviewer(
        string $text,
        int    $numQuestions,
        string $difficulty,
        string $questionType,
        string $answerLength
    ): array {
        $prompt = $this->buildPrompt($text, $numQuestions, $difficulty, $questionType, $answerLength);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.4,
                'topK'            => 32,
                'topP'            => 1,
                'maxOutputTokens' => 8192,
            ],
        ];

        $client  = \Config\Services::curlrequest();
        $url     = $this->endpoint . '?key=' . urlencode($this->apiKey);

        try {
            $response = $client->post($url, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode($payload),
                'timeout' => 90,
                'verify'  => false,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to reach the Gemini API: ' . $e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $body       = $response->getBody();

        if ($statusCode !== 200) {
            $errData = json_decode($body, true);
            $errMsg  = $errData['error']['message'] ?? 'Unknown API error (HTTP ' . $statusCode . ')';
            throw new \RuntimeException('Gemini API error: ' . $errMsg);
        }

        $data = json_decode($body, true);

        if (! isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \RuntimeException('Unexpected response structure from Gemini API.');
        }

        $rawText = $data['candidates'][0]['content']['parts'][0]['text'];

        return $this->parseJsonFromResponse($rawText);
    }

    /**
     * Build the structured prompt sent to Gemini.
     */
    private function buildPrompt(
        string $text,
        int    $numQuestions,
        string $difficulty,
        string $questionType,
        string $answerLength
    ): string {
        $typeInstructions = match ($questionType) {
            'qa'              => 'Generate Question and Answer pairs. Each item must have: "question" and "answer" fields.',
            'multiple_choice' => 'Generate Multiple Choice questions. Each item must have: "question", "choices" (array of 4 strings labeled A-D), and "answer" (the correct choice letter and text, e.g., "A. Paris").',
            'identification'  => 'Generate Identification questions where the student fills in a single word or short phrase. Each item must have: "question" and "answer" fields.',
            'mixed'           => 'Generate a mix of question types: some Question-Answer, some Multiple Choice, some Identification. Each item must have: "question", "answer", and optionally "choices" if it is a Multiple Choice item. Include a "type" field with values "qa", "multiple_choice", or "identification".',
            default           => 'Generate Question and Answer pairs. Each item must have: "question" and "answer" fields.',
        };

        $answerInstruction = $answerLength === 'detailed'
            ? 'Answers should be thorough and detailed (2-4 sentences).'
            : 'Answers should be concise (one sentence or a few words).';

        $difficultyInstruction = match ($difficulty) {
            'easy'   => 'Questions should test basic recall and simple understanding.',
            'medium' => 'Questions should test understanding and application of concepts.',
            'hard'   => 'Questions should test deep analysis, evaluation, and synthesis.',
            default  => 'Questions should test understanding and application of concepts.',
        };

        $maxTextLength = 20000;
        if (mb_strlen($text) > $maxTextLength) {
            $text = mb_substr($text, 0, $maxTextLength) . '... [text truncated]';
        }

        return <<<PROMPT
You are an expert academic reviewer generator. Using ONLY the document content provided below, generate exactly {$numQuestions} unique review questions.

RULES:
- Base all questions strictly on the provided document. Do not invent facts not present in the text.
- Do not repeat questions or use very similar phrasing.
- Difficulty level: {$difficulty}. {$difficultyInstruction}
- {$typeInstructions}
- {$answerInstruction}
- Return ONLY a valid JSON array. No explanations, no markdown, no code fences. The output must be parseable by json_decode().

JSON FORMAT EXAMPLE (adjust fields based on question type):
[
  {
    "question": "What is the main topic of the document?",
    "answer": "The main topic is...",
    "type": "qa"
  }
]

DOCUMENT CONTENT:
{$text}

Generate exactly {$numQuestions} questions now. Return ONLY the JSON array.
PROMPT;
    }

    /**
     * Extract and parse the JSON array from Gemini raw text output.
     *
     * @throws \RuntimeException
     */
    private function parseJsonFromResponse(string $rawText): array
    {
        // Strip markdown code fences if present
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($rawText));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        // Try to extract a JSON array if extra text is present
        if (preg_match('/\[.*\]/s', $cleaned, $matches)) {
            $cleaned = $matches[0];
        }

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse JSON from Gemini response: ' . json_last_error_msg());
        }

        if (! is_array($decoded) || empty($decoded)) {
            throw new \RuntimeException('Gemini returned an empty or invalid question array.');
        }

        // Validate each question has required fields
        foreach ($decoded as $index => $item) {
            if (! isset($item['question']) || ! isset($item['answer'])) {
                throw new \RuntimeException("Question at index {$index} is missing required fields.");
            }
        }

        return $decoded;
    }
}
