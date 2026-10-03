<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\DocumentExtractor;
use App\Libraries\GeminiService;
use App\Models\ReviewerHistoryModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;

class Reviewer extends BaseController
{
    private ReviewerHistoryModel $model;

    public function __construct()
    {
        $this->model = new ReviewerHistoryModel();
    }

    // â”€â”€ Upload Page â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function index(): string
    {
        return view('reviewer/upload', [
            'pageTitle' => 'Upload Document',
        ]);
    }

    // â”€â”€ Process Upload & Generate â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function generate(): RedirectResponse|string
    {
        // CSRF is handled automatically by CI4 filter

        // Validate form fields
        $rules = [
            'num_questions'  => 'required|in_list[10,20,30,50]',
            'difficulty'     => 'required|in_list[easy,medium,hard]',
            'question_type'  => 'required|in_list[qa,multiple_choice,identification,mixed]',
            'answer_length'  => 'required|in_list[short,detailed]',
            'reviewer_title' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/reviewer')->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Validate file upload
        $file = $this->request->getFile('document');

        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return redirect()->to('/reviewer')->withInput()
                ->with('error', 'Please select a valid file to upload.');
        }

        // Extract text
        $extractor = new DocumentExtractor();
        try {
            $extractedText = $extractor->extract($file);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to('/reviewer')->withInput()
                ->with('error', esc($e->getMessage()));
        } catch (\RuntimeException $e) {
            log_message('error', '[Reviewer] Extract error: ' . $e->getMessage());
            return redirect()->to('/reviewer')->withInput()
                ->with('error', 'Failed to process the file. Please try a different file.');
        }

        // Gather settings
        $numQuestions = (int) $this->request->getPost('num_questions');
        $difficulty   = $this->request->getPost('difficulty');
        $questionType = $this->request->getPost('question_type');
        $answerLength = $this->request->getPost('answer_length');
        $docName      = $file->getClientName();
        $title        = trim($this->request->getPost('reviewer_title') ?? '');
        if (empty($title)) {
            $title = 'Reviewer â€” ' . pathinfo($docName, PATHINFO_FILENAME);
        }

        // Generate via Gemini
        try {
            $gemini    = new GeminiService();
            $questions = $gemini->generateReviewer(
                $extractedText,
                $numQuestions,
                $difficulty,
                $questionType,
                $answerLength
            );
        } catch (\RuntimeException $e) {
            log_message('error', '[Reviewer] Gemini error: ' . $e->getMessage());
            $safeMsg = str_contains($e->getMessage(), 'API key') 
                ? $e->getMessage() 
                : 'AI generation failed. Please try again later.';
            return redirect()->to('/reviewer')->withInput()
                ->with('error', esc($safeMsg));
        }

        // Save to history
        $historyId = $this->model->insert([
            'document_name'  => $docName,
            'reviewer_title' => $title,
            'difficulty'     => $difficulty,
            'question_type'  => $questionType,
            'num_questions'  => $numQuestions,
            'answer_length'  => $answerLength,
            'questions_json' => json_encode($questions, JSON_UNESCAPED_UNICODE),
        ]);

        if (! $historyId) {
            log_message('error', '[Reviewer] Failed to save reviewer to DB.');
        }

        // Store in session for the reviewer display page
        session()->setFlashdata('reviewer_data', [
            'id'            => $historyId,
            'title'         => $title,
            'document_name' => $docName,
            'difficulty'    => $difficulty,
            'question_type' => $questionType,
            'num_questions' => $numQuestions,
            'answer_length' => $answerLength,
            'questions'     => $questions,
        ]);

        return redirect()->to('/reviewer/view/' . (int) $historyId);
    }

    // â”€â”€ View Reviewer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function view(int $id): string|RedirectResponse
    {
        $record = $this->model->getReviewer($id);

        if (! $record) {
            return redirect()->to('/reviewer')->with('error', 'Reviewer not found.');
        }

        $questions = json_decode($record['questions_json'], true) ?? [];

        return view('reviewer/view', [
            'pageTitle' => esc($record['reviewer_title']),
            'record'    => $record,
            'questions' => $questions,
        ]);
    }

    // â”€â”€ History â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function history(): string
    {
        $perPage = 12;
        $records = $this->model->getHistory($perPage);
        $pager   = $this->model->pager;

        return view('reviewer/history', [
            'pageTitle' => 'Reviewer History',
            'records'   => $records,
            'pager'     => $pager,
        ]);
    }

    // â”€â”€ Delete Single â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function delete(int $id): RedirectResponse
    {
        $record = $this->model->getReviewer($id);

        if (! $record) {
            return redirect()->to('/reviewer/history')->with('error', 'Reviewer not found.');
        }

        $this->model->delete($id);
        return redirect()->to('/reviewer/history')->with('success', 'Reviewer deleted successfully.');
    }

    // â”€â”€ Clear All History â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function clearHistory(): RedirectResponse
    {
        $this->model->clearAll();
        return redirect()->to('/reviewer/history')->with('success', 'All reviewer history cleared.');
    }
}
