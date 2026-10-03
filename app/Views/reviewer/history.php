<?= view('reviewer/_header') ?>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="rq-page-header">
  <div>
    <h1>Reviewer History</h1>
    <p class="subtitle">All previously generated reviewers are listed below.</p>
  </div>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap">
    <a href="<?= base_url('/reviewer') ?>" class="btn-rq btn-rq-primary btn-rq-sm">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      New Reviewer
    </a>
    <?php if (!empty($records)): ?>
    <form method="post" action="<?= base_url('/reviewer/clear-history') ?>"
          onsubmit="return confirm('Clear all reviewer history? This cannot be undone.')">
      <?= csrf_field() ?>
      <button type="submit" class="btn-rq btn-rq-danger btn-rq-sm">Clear All History</button>
    </form>
    <?php endif ?>
  </div>
</div>

<!-- ── Flash messages ──────────────────────────────────────── -->
<?php if (session()->getFlashdata('success')): ?>
  <div class="rq-alert rq-alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="rq-alert rq-alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>

<!-- ── History table ────────────────────────────────────────── -->
<?php if (empty($records)): ?>
  <div class="rq-empty">
    <div class="rq-empty-icon">
      <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
    </div>
    <h3>No reviewers yet</h3>
    <p>Upload a document and generate your first reviewer.</p>
    <a href="<?= base_url('/reviewer') ?>" class="btn-rq btn-rq-primary mt-2">Get Started</a>
  </div>
<?php else: ?>

<?php
$diffLabels = ['easy'=>'Easy','medium'=>'Medium','hard'=>'Hard'];
$qtLabels   = ['qa'=>'Q & A','multiple_choice'=>'Multiple Choice','identification'=>'Identification','mixed'=>'Mixed'];
?>

<div class="rq-table-wrap">
  <table class="rq-table" id="historyTable">
    <thead>
      <tr>
        <th>#</th>
        <th>Title</th>
        <th>Document</th>
        <th>Type</th>
        <th>Difficulty</th>
        <th>Questions</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($records as $i => $r): ?>
      <tr>
        <td><?= esc($r['id']) ?></td>
        <td style="max-width:220px">
          <a href="<?= base_url('/reviewer/view/' . (int)$r['id']) ?>" style="font-weight:600;color:var(--primary)">
            <?= esc($r['reviewer_title']) ?>
          </a>
        </td>
        <td class="text-muted small" style="max-width:180px;word-break:break-all"><?= esc($r['document_name']) ?></td>
        <td><?= esc($qtLabels[$r['question_type']] ?? $r['question_type']) ?></td>
        <td>
          <?php $dc = ['easy'=>'badge-easy','medium'=>'badge-medium','hard'=>'badge-hard'][$r['difficulty']] ?? '' ?>
          <span class="rq-q-badge <?= $dc ?>"><?= esc($diffLabels[$r['difficulty']] ?? $r['difficulty']) ?></span>
        </td>
        <td><?= (int)$r['num_questions'] ?></td>
        <td class="text-muted small"><?= date('M j, Y g:ia', strtotime($r['created_at'])) ?></td>
        <td class="td-actions">
          <a href="<?= base_url('/reviewer/view/' . (int)$r['id']) ?>" class="btn-rq btn-rq-outline btn-rq-sm">Open</a>
          <form method="post" action="<?= base_url('/reviewer/delete/' . (int)$r['id']) ?>" style="display:inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn-rq btn-rq-danger btn-rq-sm"
                    onclick="return confirm('Delete this reviewer?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>

<!-- ── Pagination ──────────────────────────────────────────── -->
<?php if ($pager): ?>
<div style="margin-top:1.5rem;display:flex;justify-content:center">
  <?= $pager->links('default', 'default_full') ?>
</div>
<?php endif ?>

<?php endif ?>

<?= view('reviewer/_footer') ?>
