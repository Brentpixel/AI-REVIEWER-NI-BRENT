<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= esc($pageTitle ?? 'Q&A Reviewer') ?> ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â Q&A Reviewer Generator</title>
<script>
  // Dark mode init
  const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  if (savedTheme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
  }
</script>
  <meta name="description" content="Upload study documents and generate Q&A reviewers powered by AI." />
  <link rel="stylesheet" href="<?= base_url('css/reviewer.css?v=<?= time() ?>') ?>" />
</head>
<body>

<!-- Navigation -->
<nav class="rq-navbar">
  <a href="<?= base_url('/reviewer') ?>" class="rq-brand">
    <div class="rq-brand-icon">Q</div>
    Q&amp;A Reviewer
  </a>

  <button class="rq-hamburger" id="rqHamburger" aria-label="Toggle menu">&#9776;</button>

  <ul class="rq-nav-links" id="rqNavLinks">
    <li><a href="<?= base_url('/reviewer') ?>"         <?= str_ends_with(current_url(), '/reviewer')        ? 'class="active"' : '' ?>>Upload</a></li>
    <li><a href="<?= base_url('/reviewer/history') ?>" <?= str_contains(current_url(), '/reviewer/history') ? 'class="active"' : '' ?>>History</a></li>
    <li><button onclick="toggleTheme()" class="btn-rq btn-rq-outline" style="border:none;background:transparent;color:var(--text);font-weight:600;cursor:pointer;">Toggle Dark Mode</button></li>
  </ul>
</nav>

<main class="rq-wrapper <?= $wrapperClass ?? '' ?>">
