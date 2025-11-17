<?php

declare(strict_types=1);
session_start();

// --- Settings ---
date_default_timezone_set('Asia/Phnom_Penh');
const STORE_FILE = __DIR__ . '/messages.json';
const MAX_NAME_LEN = 60;
const MAX_MSG_LEN  = 1000;
const MAX_ITEMS_TO_SHOW = 200;

if (!file_exists(STORE_FILE)) {
  file_put_contents(STORE_FILE, json_encode([]), LOCK_EX);
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$notice = null;

function load_messages(): array
{
  $fp = fopen(STORE_FILE, 'r');
  if (!$fp) return [];
  flock($fp, LOCK_SH);
  $raw = stream_get_contents($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  $data = json_decode($raw ?: '[]', true);
  return is_array($data) ? $data : [];
}

function save_messages(array $messages): bool
{
  $fp = fopen(STORE_FILE, 'c+');
  if (!$fp) return false;
  flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  $ok = fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  return $ok;
}

// Simple rate-limit
$can_post = true;
$cooldown_seconds = 20;
if (!empty($_SESSION['last_post_time'])) {
  $elapsed = time() - (int)$_SESSION['last_post_time'];
  if ($elapsed < $cooldown_seconds) {
    $can_post = false;
    $errors[] = "Please wait " . ($cooldown_seconds - $elapsed) . "s before posting again.";
  }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['csrf_token'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $errors[] = 'Security check failed. Please refresh and try again.';
  }

  $name = trim((string)($_POST['name'] ?? ''));
  $msg  = trim((string)($_POST['comment'] ?? ''));

  if ($name === '' || $msg === '') $errors[] = 'Name and comment are required.';
  if (mb_strlen($name) > MAX_NAME_LEN) $errors[] = 'Name is too long.';
  if (mb_strlen($msg) > MAX_MSG_LEN)  $errors[] = 'Comment is too long.';

  if ($can_post && empty($errors)) {
    $messages = load_messages();
    $messages[] = [
      'name'    => $name,
      'comment' => $msg, // escape on output
      'time'    => time(),
      'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
      'ua'      => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ];
    if (count($messages) > 5000) $messages = array_slice($messages, -5000);

    if (save_messages($messages)) {
      $_SESSION['last_post_time'] = time();
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // rotate token
      header("Location: " . strtok($_SERVER['REQUEST_URI'], '?')); // PRG
      exit;
    } else {
      $errors[] = 'Unable to save your message. Check file permissions for messages.json.';
    }
  }
}

$messages = array_reverse(load_messages());
if (count($messages) > MAX_ITEMS_TO_SHOW) {
  $messages = array_slice($messages, 0, MAX_ITEMS_TO_SHOW);
}

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Greeting Message</title>
  <link rel="stylesheet" href="style1.css" />
  <style>
    :root {
      --ink: #2b2b2b;
      --muted: #7a7468;
      --gold: #a4874f;
      --line: #e9e2d6;
    }

    html,
    body {
      margin: 0;
      color: var(--ink);
      font-family: "Noto Sans", "Khmer OS Battambang", "Noto Sans Khmer", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    .wrap {
      max-width: 860px;
      margin: 24px auto 80px;
      padding: 0 16px;
    }

    .card {
      backdrop-filter: blur(3px);
      border-radius: 16px;
      padding: 18px;

    }

    .card+.card {
      margin-top: 16px;
    }

    .section-title {
      margin: 2px 0 12px;
      color: var(--gold);
      font-weight: 800;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .8px;
    }


    form.greeting-form {
      display: grid;
      gap: 14px;
    }

    label {
      font-size: 13px;
      color: var(--muted);
    }

    input[type="text"],
    textarea {
      width: 90%;
      border: 1px solid var(--line);
      background-color: #f3eee99f;
      padding: 14px;
      border-radius: 12px;
      font-size: 15px;
      outline: none;
      transition: border-color .15s, box-shadow .15s, background .2s;
    }

    input::placeholder,
    textarea::placeholder {
      color: #a1a1a1;
    }

    input:focus,
    textarea:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 4px rgba(212, 186, 133, .2);
    }

    textarea {
      min-height: 90px;
      resize: vertical;
    }

    @keyframes zoom-in-out {
      0% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.08);
      }

      /* zoom in */
      100% {
        transform: scale(1);
      }

      /* zoom out */
    }

    /* target the image inside your button */
    .img-btn img {
      display: block;
      max-width: 340px;
      width: 75%;
      margin-left: 34px;
      animation: zoom-in-out 2.5s ease-in-out infinite;
      /* 👈 add animation */
    }

    .img-btn {
      border: 0;
      background: transparent;
      padding: 0;
      cursor: pointer;
      display: block;
      margin: 5px auto 2px;
      transform-origin: center;
      transition: transform .18s ease, filter .18s ease;
      outline: none;
      border-radius: 14px;
    }

    .img-btn:hover {
      transform: scale(1.05);
      filter: drop-shadow(0 8px 18px rgba(80, 60, 20, .13));
    }

    .img-btn:active {
      transform: scale(.95);
    }

    .img-btn:focus-visible {
      box-shadow: 0 0 0 4px rgba(164, 135, 79, .28);
    }

    .errors,
    .notice {
      margin-bottom: 10px;
      padding: 12px 14px;
      border-radius: 12px;
      font-size: 14px;
    }

    .errors {
      background: #fff1f1;
      border: 1px solid #f4c4c4;
      color: #a33a35;
    }

    .notice {
      background: #edf9f2;
      border: 1px solid #cfeede;
      color: #1f7a4a;
    }

    .list {
      display: grid;
      gap: 12px;
    }

    .meta {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      font-size: 12px;
      color: #7d6f58;
    }

    .name {
      color: #5f4a21;
      font-weight: 800;
      padding: 4px 8px;
      background: #f3eee99f;
      border: 1px solid #f2e3c7;
      border-radius: 999px;
    }

    .msg {
      margin-top: 8px;
      line-height: 1.55;
      white-space: pre-wrap;
      word-break: break-word;
    }

    .section-title {
      text-align: center;
      margin: 10px 0 18px;
      font-weight: 800;
      color: #8d7846;
      font-size: 14px;
      letter-spacing: .8px;
      position: relative;
    }

    .section-title span {
      display: block;
      margin-bottom: 6px;
    }

    .section-title .line {
      display: block;
      margin: 0 auto;
      max-width: 260px;
      height: auto;
      opacity: 0.85;
    }

    .cmt {
      background: #f3eee99f;
      border-radius: 10px;
      padding: 14px 16px;
      text-align: center;
      color: #3a2d14;
      font-size: 14px;
      margin-bottom: 16px;
    }

    .cmt-title {
      font-weight: 700;
      font-size: 15px;
      margin: 0 0 6px;
      color: #5a4a22;
    }

    .cmt .line {
      display: block;
      margin: 6px auto 10px;
      max-width: 240px;
      height: auto;
      opacity: 0.9;
    }

    .cmt-msg {
      display: block;
      font-style: italic;
      color: #444;
      margin-bottom: 6px;
    }

    .cmt-time {
      font-size: 12px;
      color: #777;
    }

    .cmt {
      background: #f3eee99f;
      border-radius: 10px;
      padding: 10px 12px;
      text-align: center;
      color: #6a5a34;
      font-size: 13px;
      margin-bottom: 12px;
    }

    .cmt p {
      margin: 0 0 4px;
      font-weight: 700;
    }

    .cmt span {
      color: #5b4a26;
    }
  </style>
</head>

<body>
  <div class="wrap">

    <div class="card">
      <?php if (!empty($errors)): ?>
        <div class="errors"><?= e(implode("\n", $errors)); ?></div>
      <?php elseif ($notice): ?>
        <div class="notice"><?= e($notice); ?></div>
      <?php endif; ?>

      <form method="post" action="" class="greeting-form">
        <label for="name">Name</label>
        <input id="name" name="name" type="text" maxlength="<?= MAX_NAME_LEN ?>" placeholder="Your name" required>

        <label for="comment">Comment</label>
        <textarea id="comment" name="comment" maxlength="<?= MAX_MSG_LEN ?>" placeholder="Write your greeting..." required></textarea>

        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']); ?>">

        <button class="img-btn  fade-in-bottom" type="submit">
          <img src="btn-greeting.png" alt="Send Greeting Messages">
        </button>
      </form>
    </div>

    <div class="card">


      <div class="cmt">
        <p>☻ Sambot Online</p>
        <img class="line" src="line-name.png" alt="divider line">
        <span>"Congratulations" ☚</span><br>
        22-06-2025 | 04:26am
      </div>


      <div class="list">
        <?php if (empty($messages)): ?>
          <div class="item"></div>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <div class="item">
              <div class="meta">
                <span class="name"><?= e($m['name'] ?? 'Anonymous'); ?></span>
                <span>•</span>
                <span><?= date('d-m-Y | h:ia', (int)($m['time'] ?? time())); ?></span>
              </div>
              <div class="msg"><?= nl2br(e($m['comment'] ?? '')); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</body>

</html>