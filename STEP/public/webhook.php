<?php

// webhook.php – pošle správu do Discordu cez webhook

// Discord → Server Settings → Integrations → Webhooks → Copy Webhook URL
const DISCORD_WEBHOOK_URL = 'https://discord.com/api/webhooks/1569706693565227019/ycXklfVWVZ65Oeqc1O9O26nLkfolF3OdfWhwvAn5onM-JYUsep3VSC0aOucmAEhISpEM';

function posliNaDiscord(string $sprava): void
{
    $ch = curl_init(DISCORD_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'content' => $sprava,
            'allowed_mentions' => ['parse' => []], // "@everyone" v názve úlohy nikoho neoznačí
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5, // keď Discord nereaguje, nástenka nezamrzne
    ]);

    $odpoved = curl_exec($ch);
    $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($odpoved === false || $kod >= 300) {
        error_log("Discord webhook zlyhal ($kod): " . curl_error($ch) . ' ' . $odpoved);
    }
    curl_close($ch);
}

// Volá sa po vytvorení úlohy (create_task.php, team_leader_dashboard.php)
function oznamNovuUlohu(string $nazov): void
{
    posliNaDiscord('🆕 ' . $_SESSION['name'] . ' vytvoril(a) novú úlohu: **' . $nazov . '**');
}

// Volá sa po zmene stavu na "Done" (Kanban, detail úlohy, moje úlohy, dashboard vedúceho)
function oznamHotovuUlohu(string $nazov): void
{
    posliNaDiscord('✅ ' . $_SESSION['name'] . ' dokončil(a) úlohu: **' . $nazov . '**');
}
