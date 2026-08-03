<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Financeiro</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,700|fraunces:600" rel="stylesheet">
    <style>
        :root {
            --bg-1: #e8f2ef;
            --bg-2: #f7faf8;
            --ink: #16352d;
            --muted: #4d6b62;
            --line: #c5ddd4;
            --accent: #1f6b57;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 2rem;
            color: var(--ink);
            font-family: "DM Sans", sans-serif;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, #cfe6dc 0%, transparent 55%),
                radial-gradient(ellipse 70% 50% at 90% 90%, #d9ebe4 0%, transparent 50%),
                linear-gradient(160deg, var(--bg-1), var(--bg-2));
        }

        main {
            width: min(100%, 34rem);
            text-align: center;
            animation: rise .7s ease both;
        }

        .brand {
            font-family: Fraunces, Georgia, serif;
            font-size: clamp(2.4rem, 6vw, 3.4rem);
            font-weight: 600;
            letter-spacing: -0.03em;
            line-height: 1.05;
            color: var(--accent);
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-top: 1.25rem;
            color: var(--muted);
            font-size: .95rem;
            font-weight: 500;
        }

        .status::before {
            content: "";
            width: .55rem;
            height: .55rem;
            border-radius: 50%;
            background: #2f9e73;
            box-shadow: 0 0 0 0 rgba(47, 158, 115, .45);
            animation: pulse 2.2s ease-in-out infinite;
        }

        p {
            margin-top: 1rem;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.55;
        }

        .note {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            color: #6b857c;
            font-size: .88rem;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(47, 158, 115, .4); }
            50% { box-shadow: 0 0 0 8px rgba(47, 158, 115, 0); }
        }
    </style>
</head>
<body>
    <main>
        <h1 class="brand">Financeiro</h1>
        <div class="status">Serviço em operação</div>
        <p>Sistema de cobrança integrado ao SIGO.<br>Não há acesso público por esta página.</p>
        <p class="note">Uso interno · autenticação via Sigoweb</p>
    </main>
</body>
</html>
