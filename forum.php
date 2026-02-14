<?php
$csvFile = "posts.csv";
$usernamesFile = "usernames.csv";

$janitors = [];
$repairmen = [];
$supporters = [];

$protectedUsernames = []; // username => password

if (file_exists($usernamesFile)) {
  if (($fp = fopen($usernamesFile, "r")) !== false) {
    while (($row = fgetcsv($fp)) !== false) {
      $name = trim($row[0]);
      $password = trim($row[1] ?? "");
      $role = strtolower(trim($row[2]));

      if ($role === 'janitor') $janitors[] = $name;
      if ($role === 'repairman') $repairmen[] = $name;
      if ($role === 'supporter') $supporters[] = $name;

      if ($password !== "") {
        $protectedUsernames[$name] = $password;
      }
    }
    fclose($fp);
  }
}

if (isset($_GET['check'])) {
  header('Content-Type: application/json');

  if (!file_exists($csvFile)) {
    echo json_encode(["mtime" => null]);
    exit;
  }

  echo json_encode([
    "mtime" => filemtime($csvFile)
  ]);
  exit;
}

$error = "";

function markdown($text) {
  $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

  $text = str_replace("\\\n", "\\n", $text);

  $codeBlocks = [];

  // Multiline code block ```code```
  $text = preg_replace_callback(
    '/```(\w*)\n(.*?)\n```/s',
    function ($matches) use (&$codeBlocks) {
      $lang = $matches[1];
      $code = trim($matches[2]);

      $placeholder = "\x1ACODEBLOCK_" . count($codeBlocks) . "\x1A";

      $br = $lang !== "" ? "<br>" : "";

      $codeBlocks[$placeholder] =
        '<pre class="codeblock"><code class="lang">' . $lang . '</code>' .
        $br .
        '<code>' . $code . '</code></pre>';

      return $placeholder;
    },
    $text
  );

  // Inline code ``code``
  $text = preg_replace_callback(
    '/``(.*?)``/',
    function ($matches) use (&$codeBlocks) {
      $code = trim($matches[1]);

      $placeholder = "\x1AINLINECODE_" . count($codeBlocks) . "\x1A";

      $codeBlocks[$placeholder] =
        '<code class="codeblock-inline">' . $code . '</code>';

      return $placeholder;
    },
    $text
  );

  // Gaytext ~*~text~*~
  $text = preg_replace('/\~\*\~(.*?)\~\*\~/', '<span class="gaytext">$1</span>', $text);

  // Bold & italic ***text***
  $text = preg_replace('/\*\*\*(.*?)\*\*\*/', '<b><i>$1</i></b>', $text);

  // Bold **text**
  $text = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $text);

  // Italic *text*
  $text = preg_replace('/\*(.*?)\*/', '<i>$1</i>', $text);

  // Underlined __text
  $text = preg_replace('/\_\_(.*?)\_\_/', '<u>$1</u>', $text);
  // Underlined _text
  $text = preg_replace('/\_(.*?)\_/', '<u>$1</u>', $text);

  // Strikethrough --text--
  $text = preg_replace('/\-\-(.*?)\-\-/', '<s>$1</s>', $text);
  // Strikethrough ~~text~~
  $text = preg_replace('/\~\~(.*?)\~\~/', '<s>$1</s>', $text);

  // Large # text
  $text = preg_replace(
    '/(^|\n)\# (.*?)(?=\n|$)/',
    '$1<span class="bigtext">$2</span>',
    $text
  );

  // Tiny -# text
  $text = preg_replace(
    '/(^|\n)\-\# (.*?)(?=\n|$)/',
    '$1<span class="smalltext">$2</span>',
    $text
  );
  // Tiny .:text:.
  $text = preg_replace('/\.\:(.*?)\:\./', '<sub>$1</sub>', $text);

  // Hyperlink [text](http(s)://url.com)
  $text = preg_replace(
    '/\[(.*?)\]\((https?:\/\/[^\s]+)\)/',
    '<a href="$2">$1</a>',
    $text
  );

  // Link http(s)://url.com
  $text = preg_replace(
    '~(?<!href=")(https?://[^\s]+)~',
    '<a href="$1">$1</a>',
    $text
  );

  // Post link @1
  $text = preg_replace_callback('/@(\d+)/', function($matches) {
    $id = intval($matches[1]);
    return '<a class="postlink" href="#post-'.$id.'">@'.$id.'</a>';
  }, $text);

  // Spoiler ||text||
  $text = preg_replace(
    '/\|\|(.*?)\|\|/s',
    '<span class="spoiler">$1</span>',
    $text
  );

  // Greentext >text
  $text = preg_replace(
    '/(^|\n)&gt;(.*?)(?=\n|$)/',
    '$1<span class="greentext">&gt;$2</span>',
    $text
  );
  // Orangetext <text
  $text = preg_replace(
    '/(^|\n)&lt;(.*?)(?=\n|$)/',
    '$1<span class="orangetext">&lt;$2</span>',
    $text
  );
  // Bluetext ^text
  $text = preg_replace(
    '/(^|\n)\^(.*?)(?=\n|$)/',
    '$1<span class="bluetext">^$2</span>',
    $text
  );
  // Glowtext (((text)))
  $text = preg_replace(
    '/\(\(\((.*?)\)\)\)/',
    '<span class="glowtext">((($1)))</span>',
    $text
  );
  // Shinetext %%text%%
  $text = preg_replace(
    '/\%\%(.*?)\%\%/',
    '<span class="shinetext">$1</span>',
    $text
  );
  // Ragetext !!!text!!!
  $text = preg_replace(
    '/\!\!\!(.*?)\!\!\!/',
    '<span class="ragetext">$1!!!</span>',
    $text
  );
  // Spintext &-text-
  $text = preg_replace('/&amp;-(.*?)-/', '<span class="spintext">$1</span>', $text);

  // Comment // text
  $text = preg_replace(
    '/(^|\n)\/\/\ ?(.*?)(?=\n|$)/',
    '$1<span class="comment">$2</span>',
    $text
  );

  foreach ($codeBlocks as $placeholder => $html) {
    $text = str_replace($placeholder, $html, $text);
  }

  return nl2br($text);
}

function renderUsername($name, $janitors, $repairmen, $supporters, $client) {
  $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

  $isEmacs = ($client === 'emacs');

  if ($isEmacs) {
    return '<span class="emacs-user"><strong>' . $safeName . '</strong> # Emacs user</span>';
  }

  if (in_array($name, $janitors, true)) {
    return '<span class="janitor"><strong>' . $safeName . '</strong> # Janitor</span>';
  }

  if (in_array($name, $repairmen, true)) {
    return '<span class="repairman"><strong>' . $safeName . '</strong> # Repairman</span>';
  }

  if (in_array($name, $supporters, true)) {
    return '<span class="supporter"><strong>' . $safeName . '</strong> # Supporter</span>';
  }

  return '<strong>' . $safeName . '</strong>';
}

function detectClient() {
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ua = strtolower($ua);

  if (strpos($ua, 'emacs') !== false || strpos($ua, 'eww') !== false) {
    return 'emacs';
  }

  return '';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $rawName = trim($_POST["name"] ?? "Anonymous");
  $rawName = str_replace(["\n", "\r"], "", $rawName);

  $authOK = false;

  $name = $rawName;
  $passwordAttempt = null;

  if (strpos($rawName, "&#") !== false) {
    list($name, $passwordAttempt) = explode("&#", $rawName, 2);
    $name = trim($name);
    $passwordAttempt = trim($passwordAttempt);
  }

  if (isset($protectedUsernames[$name])) {
    if ($passwordAttempt === null) {
      $error = "Username is password protected!";
    } elseif ($passwordAttempt !== $protectedUsernames[$name] || $passwordAttempt === "") {
      $error = "Password for the username incorrect!";
    } else {
      $authOK = true; // correct password
    }
  } else {
    if ($passwordAttempt !== null && $passwordAttempt !== "") {
      $error = "Username not password protected, use of \"username&#password\" format is pointless!";
    } else {
      $authOK = true; // password not required
    }
  }

  if ($authOK) {
    $content = trim($_POST["postcontent"] ?? "");

    date_default_timezone_set('Europe/Budapest');
    $time = date("Y/m/d H:i:s");

    $ip = $_SERVER['REMOTE_ADDR'];
    $client = detectClient();

    if (mb_strlen($name, 'UTF-8') > 50) {
      $error = "Username can only be at most 50 characters long!";
    } elseif (mb_strlen($content, 'UTF-8') > 1000) {
      $error = "Post text can only be at most 1000 characters long!";
    } elseif ($content !== "") {
      if ($name === "") {
        $name = "Anonymous";
      } else {
        setcookie(
          "forum_username",
          $name,
          time() + 60 * 60 * 24 * 30,
          "/",
          "",
          true,
          true
        );
      }

      $content = str_replace("\\", "\\\\", $content);
      $content = str_replace(["\r\n", "\r", "\n"], "\\n", $content);

      $id = 1;
      if (file_exists($csvFile)) {
        $fp = fopen($csvFile, "r");
        $lastRow = null;
        while (($row = fgetcsv($fp, 0, ",", '"', "\\")) !== false) {
          $lastRow = $row;
        }
        fclose($fp);
        if ($lastRow) {
          $id = intval($lastRow[0]) + 1;
        }
      }

      $fp = fopen($csvFile, "a");
      fputcsv($fp, [$id, $time, $name, $content, $ip, $client]);
      fclose($fp);

      header("Location: " . $_SERVER["PHP_SELF"]);
      exit;
    }
  }
}

$posts = [];
if (file_exists($csvFile)) {
  $fp = fopen($csvFile, "r");
  while (($row = fgetcsv($fp, 0, ",", '"', "\\")) !== false) {
    $posts[] = $row;
  }
  fclose($fp);
}
?>

<!DOCTYPE html>
<html lang="hu">
  <head>
    <title>Forum</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="favicon.png" type="image/x-icon">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
  </head>
  <body>
    <div class="topbar">
      <div class="title">Forum</div>
      <ul>
        <li class="menubutton"><a href="help.html">?</a></li>
      </ul>
    </div>

    <div class="content">
      <form method="post" class="post-form">
        <?php if ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php $savedName = $_COOKIE["forum_username"] ?? ""; ?>
        <input class="username" type="text" name="name" autocomplete="off" placeholder="Username" value="<?= htmlspecialchars($savedName, ENT_QUOTES, 'UTF-8') ?>">
        <textarea name="postcontent" autocomplete="off" placeholder="Text (Markdown++)" required></textarea>
        <button type="submit">Post</button>
      </form>

      <div class="posts">
        <?php foreach (array_reverse($posts) as $post): ?>
          <?php
            $rawContent = $post[3];
            $rawContent = str_replace("\\n", "\n", $rawContent);
            $rawContent = str_replace("\\\\", "\\", $rawContent);
            $rawContent = str_replace("\\\\n", "\\n", $rawContent); 

            $isDeleted = preg_match('/^\[DELETED:?\s*.+\]$/u', trim($rawContent));
          ?>
          <div class="post <?= $isDeleted ? ' deleted' : '' ?>" id="post-<?= htmlspecialchars($post[0]) ?>">

            <div class="meta">
              <?php $client = $post[5] ?? ''; ?>
              <?= renderUsername($post[2], $janitors, $repairmen, $supporters, $client) ?>
                <span> | <?= htmlspecialchars($post[1]) ?> | <a href="#" class="replylink" data-id="<?= htmlspecialchars($post[0]) ?>">#<?= htmlspecialchars($post[0]) ?></a></span>
            </div>
            <div class="postbody">
              <?= markdown($rawContent) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </body>

  <script>
    let lastMTime = null;
    let seenPosts = new Set();

    function initSeenPosts() {
      document.querySelectorAll('.post[id^="post-"]').forEach(post => {
        seenPosts.add(post.id);
      });
    }

    async function checkForUpdates() {
      try {
        const res = await fetch(window.location.pathname + '?check=1', {
          cache: 'no-store'
        });
        const data = await res.json();

        if (lastMTime === null) {
          lastMTime = data.mtime;
          initSeenPosts();
          return;
        }

        if (data.mtime && data.mtime !== lastMTime) {
          lastMTime = data.mtime;
          await reloadPosts();
        }
      } catch (e) {
        console.error(e);
      }
    }

    async function reloadPosts() {
      const res = await fetch(window.location.pathname, { cache: 'no-store' });
      const html = await res.text();

      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const newPostsContainer = doc.querySelector('.posts');

      const currentContainer = document.querySelector('.posts');
      currentContainer.innerHTML = newPostsContainer.innerHTML;

      document.querySelectorAll('.post[id^="post-"]').forEach(post => {
        if (!seenPosts.has(post.id)) {
          seenPosts.add(post.id);

          post.classList.add('new');
          void post.offsetWidth;

          setTimeout(() => post.classList.add('fade-out'), 10000);
          setTimeout(() => post.classList.remove('new', 'fade-out'), 15000);
        }
      });
    }

    setInterval(checkForUpdates, 1000);

    document.addEventListener("DOMContentLoaded", () => {
      const textarea = document.querySelector('textarea[name="postcontent"]');

      document.addEventListener('click', (e) => {
        const link = e.target.closest('.replylink');
        if (!link) return;

        e.preventDefault();

        const postId = link.dataset.id;

        // Scroll to top smoothly
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });

        // Insert @id into textarea at cursor position
        if (textarea) {
          textarea.focus();

          const text = textarea.value;
          const start = textarea.selectionStart;
          const end = textarea.selectionEnd;

          const insertText = '@' + postId + ' ';

          textarea.value =
            text.substring(0, start) +
            insertText +
            text.substring(end);

          // Move cursor after inserted text
        const newCursorPos = start + insertText.length;
          textarea.setSelectionRange(newCursorPos, newCursorPos);
        }
      });
    });

    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll('.posts a[href^="#post-"]').forEach(link => {
        link.addEventListener('click', (e) => {
          e.preventDefault();

          const targetId = link.getAttribute('href').substring(1);
          const targetPost = document.getElementById(targetId);

          if (targetPost) {
            targetPost.scrollIntoView({ behavior: 'smooth', block: 'center' });

            targetPost.classList.remove('highlight', 'fade-out');
            void targetPost.offsetWidth;

            targetPost.classList.add('highlight');

            setTimeout(() => {
              targetPost.classList.add('fade-out');
            }, 1000);

            setTimeout(() => {
              targetPost.classList.remove('highlight', 'fade-out');
            }, 3000);
          }
        });
      });
    });
  </script>
</html>
