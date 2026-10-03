<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ReviewerHistoryModel extends Model
{
    protected $table            = 'reviewer_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'document_name',
        'reviewer_title',
        'difficulty',
        'question_type',
        'num_questions',
        'answer_length',
        'questions_json',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'document_name'  => 'required|max_length[255]',
        'reviewer_title' => 'required|max_length[255]',
        'difficulty'     => 'required|in_list[easy,medium,hard]',
        'question_type'  => 'required|in_list[qa,multiple_choice,identification,mixed]',
        'num_questions'  => 'required|in_list[10,20,30,50]',
        'answer_length'  => 'required|in_list[short,detailed]',
        'questions_json' => 'required',
    ];

    /**
     * Return paginated history, newest first.
     */
    public function getHistory(int $perPage = 10): array
    {
        return $this->orderBy('created_at', 'DESC')->paginate($perPage);
    }

    /**
     * Return a single reviewer record by ID.
     */
    public function getReviewer(int $id): ?array
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Clear all reviewer history records.
     */
    public function clearAll(): int
    {
        $this->db->table($this->table)->truncate();
        return $this->db->affectedRows();
    }
}
