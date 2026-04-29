<?php

function sanitize(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flashMessage(string $key, string $message = null): ?string
{
    if ($message !== null) {
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['flash_messages'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION)) {
        session_start();
    }

    if (!empty($_SESSION['flash_messages'][$key])) {
        $message = $_SESSION['flash_messages'][$key];
        unset($_SESSION['flash_messages'][$key]);
        return $message;
    }

    return null;
}

function displayFlash(string $key): void
{
    $message = flashMessage($key);

    if ($message !== null) {
        echo '<div class="flash-message">' . sanitize($message) . '</div>';
    }
}
