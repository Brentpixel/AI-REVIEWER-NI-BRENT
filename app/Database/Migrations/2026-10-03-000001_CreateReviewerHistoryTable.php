<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReviewerHistoryTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'document_name'  => ['type'=>'VARCHAR','constraint'=>255],
            'reviewer_title' => ['type'=>'VARCHAR','constraint'=>255],
            'difficulty'     => ['type'=>'ENUM','constraint'=>['easy','medium','hard'],'default'=>'medium'],
            'question_type'  => ['type'=>'ENUM','constraint'=>['qa','multiple_choice','identification','mixed'],'default'=>'qa'],
            'num_questions'  => ['type'=>'TINYINT','constraint'=>3,'unsigned'=>true,'default'=>10],
            'answer_length'  => ['type'=>'ENUM','constraint'=>['short','detailed'],'default'=>'short'],
            'questions_json' => ['type'=>'LONGTEXT'],
            'created_at'     => ['type'=>'DATETIME','null'=>true],
            'updated_at'     => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('reviewer_history', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('reviewer_history', true);
    }
}
