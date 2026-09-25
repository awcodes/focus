<?php

declare(strict_types=1);

/*
 * A tiny stand-in for a Workbench application, served with `php -S` by the browser tests.
 */

$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

// Tests share state with the server through files: a login counter, and a session generation that a test can
// bump to invalidate every existing session, the way rebuilding a Workbench does.
$state = getenv('FOCUS_FIXTURE_STATE') ?: sys_get_temp_dir();
$generation = (int) @file_get_contents("{$state}/generation");
$authenticated = ($_COOKIE['session'] ?? null) === "ok-{$generation}";

$layout = static function (string $body): void {
    echo <<<HTML
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                :root { color-scheme: light dark; }
                body { margin: 0; font: 16px/1.5 sans-serif; background: #ffffff; color: #111111; }
                @media (prefers-color-scheme: dark) { body { background: #111111; color: #eeeeee; } }
                .card { margin: 40px; padding: 20px; border: 1px solid #888; width: 300px; }
                .tiny { display: inline-block; width: 32px; height: 32px; background: #6366f1; }
                .spin { animation: spin 1s linear infinite; }
                @keyframes spin { to { transform: rotate(360deg); } }
                .tall { height: 3000px; }
                .modal { display: none; }
                .modal.open { display: block; }
                button:hover { background: rgb(255, 0, 0); }
                button:focus { outline: 6px solid rgb(0, 255, 0); }
                .panel { width: 200px; height: 100px; background: #6366f1; }
            </style>
        </head>
        <body>{$body}</body>
        </html>
        HTML;
};

switch ($path) {
    case '/admin/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            file_put_contents("{$state}/logins", ((int) @file_get_contents("{$state}/logins")) + 1);

            if (($_POST['email'] ?? '') === 'test@example.com' && ($_POST['password'] ?? '') === 'password') {
                setcookie('session', "ok-{$generation}", ['path' => '/']);
                header('Location: /admin');

                return;
            }

            if (($_POST['password'] ?? '') === 'throttle') {
                header('Location: /admin/login?throttled=1');

                return;
            }

            header('Location: /admin/login?failed=1');

            return;
        }

        if ($authenticated) {
            header('Location: /admin');

            return;
        }

        $throttled = isset($_GET['throttled']) ? '<p>Too many login attempts. Please try again in 60 seconds.</p>' : '';

        $layout(<<<HTML
            {$throttled}
            <form method="post" action="/admin/login">
                <input type="email" name="email" value="test@example.com">
                <input type="password" name="password" value="">
                <button type="submit">Sign in</button>
            </form>
            HTML);

        return;

    case '/admin':
    case '/admin/page':
        if (! $authenticated) {
            header('Location: /admin/login');

            return;
        }

        $layout(<<<'HTML'
            <h1 id="title">Dashboard</h1>
            <p data-focus="clock"></p>
            <div class="card" data-focus="card">Card <span class="tiny" data-focus="tiny"></span></div>
            <span data-focus="dup">A</span><span data-focus="dup">B</span>
            <div data-focus="hidden" style="display: none">Hidden</div>
            <div class="spin" data-focus="spinner">*</div>
            <button data-focus-action="open" onclick="document.querySelector('.modal').classList.add('open')">Open</button>
            <div class="modal card" data-focus="modal">Modal</div>
            <div data-focus="late"></div>
            <input id="search">
            <select id="group"><option value="a">A</option><option value="b">B</option></select>
            <div class="tall"></div>
            <div data-focus="footer">Footer</div>
            <script>
                document.querySelector('[data-focus="clock"]').textContent = new Date().toISOString();
                setTimeout(() => fetch('/api/slow').then((r) => r.text()).then((text) => {
                    document.querySelector('[data-focus="late"]').textContent = text;
                }), 50);
            </script>
            HTML);

        return;

    case '/admin/frame':
        if (! $authenticated) {
            header('Location: /admin/login');

            return;
        }

        $layout(<<<'HTML'
            <h1>Framed</h1>
            <iframe id="preview" src="/admin/frame-content" style="width: 800px; height: 500px; border: 0"></iframe>
            HTML);

        return;

    case '/admin/frame-content':
        $layout(<<<'HTML'
            <div style="padding: 40px">
                <div class="panel" data-focus="panel"></div>
                <span class="dup">A</span><span class="dup">B</span>
                <div data-focus="hidden" style="display: none">Hidden</div>
                <button id="open" onclick="document.getElementById('popup').style.display = 'block'">Open</button>
                <div id="popup" class="panel" style="display: none"></div>
            </div>
            HTML);

        return;

    case '/plain':
        echo '<!doctype html><html><body style="margin: 0; background: #fff"><h1>No dark mode</h1></body></html>';

        return;

    case '/api/slow':
        usleep(400_000);
        echo 'Loaded late';

        return;

    case '/public':
        $layout('<h1>Public</h1><div class="card" data-focus="card">Public card</div>');

        return;

    default:
        http_response_code(404);
        $layout('<h1>Not found</h1>');
}
