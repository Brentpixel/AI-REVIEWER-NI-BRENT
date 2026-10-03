<?= view('reviewer/_header') ?>

<!-- ── Quiz overlay ─────────────────────────────────────────── -->
<div class="rq-quiz-overlay" id="rqQuizOverlay" role="dialog" aria-modal="true" aria-label="Quiz Mode">
  <div class="rq-quiz-box" id="rqQuizBox">
    <!-- Content injected by JS -->
  </div>
</div>

<!-- ── Loading overlay ─────────────────────────────────────── -->
<div class="rq-loading-overlay" id="rqLoadingOverlay">
  <div class="rq-spinner"></div>
  <div class="rq-loading-text">Processing...</div>
</div>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="rq-page-header">
  <div>
    <h1><?= esc($record['reviewer_title']) ?></h1>
    <p class="subtitle">Source: <?= esc($record['document_name']) ?></p>
  </div>
  <a href="<?= base_url('/reviewer') ?>" class="btn-rq btn-rq-secondary btn-rq-sm">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
    Upload Another
  </a>
</div>

<!-- ── Meta chips ───────────────────────────────────────────── -->
<div class="rq-meta-bar">
  <?php
  $diffLabels = ['easy'=>'Easy','medium'=>'Medium','hard'=>'Hard'];
  $qtLabels   = ['qa'=>'Q & A','multiple_choice'=>'Multiple Choice','identification'=>'Identification','mixed'=>'Mixed'];
  $alLabels   = ['short'=>'Short Answers','detailed'=>'Detailed Answers'];
  ?>
  <span class="rq-meta-chip"><strong><?= count($questions) ?></strong> Questions</span>
  <span class="rq-meta-chip">Difficulty: <strong><?= esc($diffLabels[$record['difficulty']] ?? $record['difficulty']) ?></strong></span>
  <span class="rq-meta-chip">Type: <strong><?= esc($qtLabels[$record['question_type']] ?? $record['question_type']) ?></strong></span>
  <span class="rq-meta-chip"><?= esc($alLabels[$record['answer_length']] ?? $record['answer_length']) ?></span>
</div>

<!-- ── Controls bar ─────────────────────────────────────────── -->
<div class="rq-controls">
  <button class="btn-rq btn-rq-secondary btn-rq-sm" id="btnShowAll">Show All Answers</button>
  <button class="btn-rq btn-rq-secondary btn-rq-sm" id="btnHideAll">Hide All Answers</button>
  <div class="spacer"></div>
  <button class="btn-rq btn-rq-dark btn-rq-sm" id="btnStartQuiz">Start Quiz Mode</button>
  <button class="btn-rq btn-rq-outline btn-rq-sm" onclick="window.print()">Print Reviewer</button>
  <a href="<?= base_url('/reviewer') ?>" class="btn-rq btn-rq-primary btn-rq-sm">Upload Another</a>
  <form method="post" action="<?= base_url('/reviewer/delete/' . $record['id']) ?>"
        style="display:inline" id="deleteForm">
    <?= csrf_field() ?>
    <button type="submit" class="btn-rq btn-rq-danger btn-rq-sm"
            onclick="return confirm('Delete this reviewer from history?')">Delete</button>
  </form>
</div>

<!-- ── Flash ───────────────────────────────────────────────── -->
<?php if (session()->getFlashdata('success')): ?>
  <div class="rq-alert rq-alert-success" style="margin-bottom:1rem">
    <?= esc(session()->getFlashdata('success')) ?>
  </div>
<?php endif ?>

<!-- ── Questions list ────────────────────────────────────────── -->
<?php if (empty($questions)): ?>
  <div class="rq-empty">
    <div class="rq-empty-icon">?</div>
    <h3>No questions found</h3>
    <p>The AI did not return valid questions. Please try again.</p>
    <a href="<?= base_url('/reviewer') ?>" class="btn-rq btn-rq-primary mt-2">Upload Another File</a>
  </div>
<?php else: ?>

<div class="rq-q-list" id="rqQList">
  <?php foreach ($questions as $i => $q): ?>
    <?php
      $num    = $i + 1;
      $type   = $q['type'] ?? $record['question_type'];
      $badgeCls = match ($type) {
        'multiple_choice' => 'badge-mc',
        'identification'  => 'badge-ident',
        default           => 'badge-qa',
      };
      $badgeLabel = match ($type) {
        'multiple_choice' => 'MC',
        'identification'  => 'ID',
        default           => 'Q&A',
      };
    ?>
    <div class="rq-q-card" data-index="<?= $i ?>" data-type="<?= esc($type) ?>"
         data-answer="<?= esc(htmlspecialchars($q['answer'] ?? '', ENT_QUOTES)) ?>">
      <div class="rq-q-header">
        <div class="rq-q-num"><?= $num ?></div>
        <div class="rq-q-text"><?= esc($q['question'] ?? '') ?></div>
        <span class="rq-q-badge <?= $badgeCls ?>"><?= $badgeLabel ?></span>
      </div>

      <?php if (!empty($q['choices']) && is_array($q['choices'])): ?>
      <div class="rq-choices">
        <?php foreach ($q['choices'] as $choice): ?>
          <div class="rq-choice-item"><?= esc($choice) ?></div>
        <?php endforeach ?>
      </div>
      <?php endif ?>

      <div class="rq-answer-wrap">
        <div class="rq-answer" id="answer-<?= $i ?>">
          <div class="rq-answer-label">Answer</div>
          <div class="rq-answer-text"><?= esc($q['answer'] ?? '') ?></div>
        </div>
        <div class="rq-answer-actions">
          <button class="btn-rq btn-rq-success btn-rq-sm btnShow"
                  data-target="answer-<?= $i ?>">Show Answer</button>
          <button class="btn-rq btn-rq-secondary btn-rq-sm btnHide d-none"
                  data-target="answer-<?= $i ?>">Hide Answer</button>
        </div>
      </div>
    </div>
  <?php endforeach ?>
</div>

<?php endif ?>

<!-- ─── JavaScript ────────────────────────────────────────── -->
<script>
(function () {

  /* ── Show / Hide answers ─────────────────────────────── */
  function setAnswerVisible(targetId, show) {
    const ans  = document.getElementById(targetId);
    const wrap = ans?.closest('.rq-answer-wrap');
    if (!ans || !wrap) return;
    ans.classList.toggle('visible', show);
    wrap.querySelector('.btnShow')?.classList.toggle('d-none', show);
    wrap.querySelector('.btnHide')?.classList.toggle('d-none', !show);
  }

  document.querySelectorAll('.btnShow').forEach(btn =>
    btn.addEventListener('click', () => setAnswerVisible(btn.dataset.target, true)));
  document.querySelectorAll('.btnHide').forEach(btn =>
    btn.addEventListener('click', () => setAnswerVisible(btn.dataset.target, false)));

  document.getElementById('btnShowAll')?.addEventListener('click', () => {
    document.querySelectorAll('.rq-q-card').forEach((_, i) => setAnswerVisible('answer-' + i, true));
  });
  document.getElementById('btnHideAll')?.addEventListener('click', () => {
    document.querySelectorAll('.rq-q-card').forEach((_, i) => setAnswerVisible('answer-' + i, false));
  });

  /* ── Quiz Mode ──────────────────────────────────────── */
  const overlay = document.getElementById('rqQuizOverlay');
  const box     = document.getElementById('rqQuizBox');
  const cards   = Array.from(document.querySelectorAll('.rq-q-card'));

  if (!cards.length) return;

  const questions = cards.map(c => ({
    question: c.querySelector('.rq-q-text').textContent.trim(),
    answer:   c.dataset.answer,
    type:     c.dataset.type,
    choices:  Array.from(c.querySelectorAll('.rq-choice-item')).map(el => el.textContent.trim()),
  }));

  let current = 0, score = 0, answered = [];

  function openQuiz() {
    current = 0; score = 0; answered = [];
    overlay.classList.add('active');
    renderQuestion();
  }

  function closeQuiz() { overlay.classList.remove('active'); }

  function renderQuestion() {
    if (current >= questions.length) { renderScore(); return; }
    const q = questions[current];
    const pct = Math.round((current / questions.length) * 100);

    let inputHtml = '';
    if (q.type === 'multiple_choice' && q.choices.length > 0) {
      inputHtml = `<div class="rq-quiz-choices">
        ${q.choices.map((c, i) => `<button class="rq-quiz-choice-btn" data-idx="${i}">${c}</button>`).join('')}
      </div>`;
    } else {
      inputHtml = `<div class="rq-quiz-input-wrap">
        <input type="text" class="rq-control" id="rqAnswer" placeholder="Type your answer..." autocomplete="off" />
        <button class="btn-rq btn-rq-primary" id="rqSubmitAnswer">Check</button>
      </div>`;
    }

    box.innerHTML = `
      <div class="rq-quiz-progress">
        <div class="rq-quiz-progress-bar"><div class="rq-quiz-progress-fill" style="width:${pct}%"></div></div>
        <div class="rq-quiz-num">${current+1} / ${questions.length}</div>
      </div>
      <div class="rq-quiz-q-text">${escHtml(q.question)}</div>
      ${inputHtml}
      <div class="rq-quiz-feedback" id="rqFeedback"></div>
      <div class="rq-quiz-footer">
        <button class="btn-rq btn-rq-secondary" id="rqCloseQuiz">Exit Quiz</button>
        <button class="btn-rq btn-rq-primary d-none" id="rqNextBtn">
          ${current + 1 < questions.length ? 'Next Question' : 'See Results'}
        </button>
      </div>`;

    document.getElementById('rqCloseQuiz').addEventListener('click', closeQuiz);
    document.getElementById('rqNextBtn')?.addEventListener('click', () => { current++; renderQuestion(); });

    if (q.type === 'multiple_choice' && q.choices.length > 0) {
      document.querySelectorAll('.rq-quiz-choice-btn').forEach(btn => {
        btn.addEventListener('click', function() { checkMCAnswer(this, q); });
      });
    } else {
      const ansInput = document.getElementById('rqAnswer');
      ansInput?.addEventListener('keydown', e => { if (e.key === 'Enter') checkTextAnswer(ansInput, q); });
      document.getElementById('rqSubmitAnswer')?.addEventListener('click', () => checkTextAnswer(ansInput, q));
    }
  }

  function checkMCAnswer(btn, q) {
    document.querySelectorAll('.rq-quiz-choice-btn').forEach(b => b.disabled = true);
    const correct = q.answer.toLowerCase().trim();
    const chosen  = btn.textContent.toLowerCase().trim();
    const isRight = correct.includes(chosen.charAt(0)) || chosen.includes(correct.charAt(0)) || chosen === correct;

    if (isRight) { btn.classList.add('correct'); score++; showFeedback(true, q.answer); }
    else {
      btn.classList.add('wrong');
      document.querySelectorAll('.rq-quiz-choice-btn').forEach(b => {
        if (b.textContent.toLowerCase().trim().startsWith(q.answer.charAt(0).toLowerCase())) b.classList.add('correct');
      });
      showFeedback(false, q.answer);
    }
    answered.push({ question: q.question, correct: isRight, correctAnswer: q.answer });
  }

  function checkTextAnswer(input, q) {
    const userAns    = input.value.trim();
    const correctAns = q.answer.trim();
    if (!userAns) return;
    input.disabled = true;
    document.getElementById('rqSubmitAnswer').disabled = true;

    const isRight = userAns.toLowerCase().replace(/\s+/g,' ') === correctAns.toLowerCase().replace(/\s+/g,' ')
                 || correctAns.toLowerCase().includes(userAns.toLowerCase());
    if (isRight) score++;
    showFeedback(isRight, correctAns);
    answered.push({ question: q.question, correct: isRight, correctAnswer: correctAns });
  }

  function showFeedback(isRight, correctAnswer) {
    const fb = document.getElementById('rqFeedback');
    fb.style.display = 'block';
    fb.className = 'rq-quiz-feedback ' + (isRight ? 'correct' : 'wrong');
    fb.innerHTML = isRight
      ? 'Correct!'
      : `Incorrect. <div class="rq-quiz-correct-answer">Correct answer: <strong>${escHtml(correctAnswer)}</strong></div>`;
    document.getElementById('rqNextBtn')?.classList.remove('d-none');
  }

  function renderScore() {
    const pct = Math.round((score / questions.length) * 100);
    const reviewHtml = answered.map((a, i) => `
      <div style="margin-bottom:.75rem;padding:.6rem .85rem;border-radius:6px;background:${a.correct ? 'rgba(22,163,74,.07)' : 'rgba(220,38,38,.07)'};border:1px solid ${a.correct ? 'rgba(22,163,74,.2)' : 'rgba(220,38,38,.2)'}">
        <div style="font-size:.78rem;font-weight:700;color:${a.correct ? '#16a34a' : '#dc2626'}">${a.correct ? 'Correct' : 'Incorrect'} — Q${i+1}</div>
        <div style="font-size:.85rem;margin-top:.25rem">${escHtml(a.question)}</div>
        ${!a.correct ? `<div style="font-size:.82rem;color:#64748b;margin-top:.2rem">Answer: <strong>${escHtml(a.correctAnswer)}</strong></div>` : ''}
      </div>`).join('');

    box.innerHTML = `
      <div class="rq-score-screen">
        <div class="rq-score-circle">${pct}%</div>
        <div class="rq-score-label">Quiz Complete!</div>
        <div class="rq-score-sub">You got <strong>${score}</strong> out of <strong>${questions.length}</strong> correct.</div>
        <div class="rq-score-review mt-2">
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.75rem">Review</div>
          ${reviewHtml}
        </div>
        <div style="display:flex;gap:.5rem;margin-top:1.25rem;flex-wrap:wrap">
          <button class="btn-rq btn-rq-primary" onclick="location.reload()">Retake Quiz</button>
          <button class="btn-rq btn-rq-secondary" id="rqCloseScore">Close</button>
        </div>
      </div>`;
    document.getElementById('rqCloseScore').addEventListener('click', closeQuiz);
  }

  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  document.getElementById('btnStartQuiz')?.addEventListener('click', openQuiz);
  overlay.addEventListener('click', e => { if (e.target === overlay) closeQuiz(); });

})();
</script>

<?= view('reviewer/_footer') ?>
