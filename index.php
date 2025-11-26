<?php
/**
 * Auto Reaction Bot - PHP Version
 * 
 * Single file PHP implementation of the Auto Reaction Bot
 * Automatically reacts to messages in Telegram chats with customizable emojis
 * Developed by @VenomDevX
 */

// Configuration - Direct Setup
$BOT_TOKEN = '8303657448:AAGVfmlNifxffGsLGpcMFigpsYq1083fV6Y';
$BOT_USERNAME = 'VenomDevX_Reaction_Bot';
$EMOJI_LIST = '👍❤🔥🥰👏😁🎉🤩🙏👌🕊😍🐳❤‍🔥💯⚡🏆';
$RANDOM_LEVEL = 0;
$RESTRICTED_CHATS = '';

// Constants
const START_MESSAGE = '👋 Hello there, %s !

Welcome to the *Auto Emoji Reaction Bot 🎉*, ready to sprinkle your conversations with a little extra happiness!

💁‍♂️ Here\'s how I spice up your chats:

*✨ DM Magic*: Message me and receive a surprise emoji in return. Expect the unexpected and enjoy the fun!
*🏖 Group & Channel*: Add me to your groups or channels, and I\'ll keep the vibe positive by reacting to messages with engaging emojis.

✍️ To view the emojis I can use, simply type /reactions.

Let\'s elevate our conversations with more energy and color! 🚀

*Developed by @VenomDevX*';

const HTML_CONTENT = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Telegram Auto Reaction Bot</title>
<meta name="description" content="Telegram Auto Reaction bot that reacts to all messages received from chats automatically. Developed by @VenomDevX">
<style>
  body, html {
    height: 100%; margin: 0; display: flex; justify-content: center; align-items: center; flex-direction: column; font-family: Arial, sans-serif;
  }
  .logo {
    width: 60%; margin-bottom: 20px;
  }
  .title { margin-bottom: 20px; font-size: 34px; font-weight: bold; color: #333; text-align: center; }
  .developer { margin-top: 20px; font-size: 14px; color: #666; }
  .button {
    padding: 10px 20px; margin: 10px; font-size: 16px; cursor: pointer; text-align: center; color: #fff; border: none; border-radius: 15px;
    transition: background-color 0.3s ease; display: inline-block; outline: none;
  }
  .githubBtn { background-color: #0881FD; }
  .githubBtn:hover { background-color: #0672E0; }
</style>
</head>
<body>

<div class="title">Telegram Auto Reaction Bot 🎉</div>

<button class="button githubBtn" onclick="window.location=\'https://t.me/VenomDevX_Reaction_Bot\'">Start Bot</button>

<div class="developer">Developed by @VenomDevX</div>

</body>
</html>';

/**
 * Telegram Bot API Class
 */
class TelegramBotAPI {
    private $apiUrl;
    
    public function __construct($botToken) {
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}/";
    }
    
    /**
     * Make API call to Telegram
     */
    private function callApi($action, $body) {
        $url = $this->apiUrl . $action;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            error_log("Telegram API request failed: {$action}");
            throw new Exception("Telegram API error: Network error");
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200 || !$data['ok']) {
            error_log("Telegram API request failed: {$action} (Status: {$httpCode})");
            if (isset($data['description'])) {
                error_log("Error description: {$data['description']}");
            }
            throw new Exception("Telegram API error: " . ($data['description'] ?? 'Unknown error'));
        }
        
        return $data;
    }
    
    /**
     * Set message reaction
     */
    public function setMessageReaction($chatId, $messageId, $emoji) {
        $this->callApi('setMessageReaction', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reaction' => [[
                'type' => 'emoji',
                'emoji' => $emoji
            ]],
            'is_big' => true
        ]);
    }
    
    /**
     * Send message
     */
    public function sendMessage($chatId, $text, $inlineKeyboard = null) {
        $body = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];
        
        if ($inlineKeyboard) {
            $body['reply_markup'] = ['inline_keyboard' => $inlineKeyboard];
        }
        
        $this->callApi('sendMessage', $body);
    }
}

/**
 * Helper Functions
 */
function getRandomPositiveReaction($reactions) {
    return $reactions[array_rand($reactions)];
}

function splitEmojis($emojiString) {
    // Split emoji string into array
    preg_match_all('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $emojiString, $matches);
    return $matches[0];
}

function getChatIds($chats) {
    if (empty($chats)) {
        return [];
    }
    return array_filter(array_map('intval', explode(',', $chats)));
}

/**
 * Handle incoming Telegram Update
 */
function onUpdate($data, $botApi, $reactions, $restrictedChats, $botUsername, $randomLevel) {
    $chatId = null;
    $messageId = null;
    $text = null;
    
    if (isset($data['message']) || isset($data['channel_post'])) {
        $content = $data['message'] ?? $data['channel_post'];
        $chatId = $content['chat']['id'];
        $messageId = $content['message_id'];
        $text = $content['text'] ?? null;
        
        // Handle /start command
        if (isset($data['message']) && ($text === '/start' || $text === '/start@' . $botUsername)) {
            $userName = $content['chat']['type'] === 'private' ? $content['from']['first_name'] : $content['chat']['title'];
            $message = sprintf(START_MESSAGE, $userName);
            
            $keyboard = [
                [
                    ['text' => '➕ Add to Channel ➕', 'url' => "https://t.me/{$botUsername}?startchannel=botstart"],
                    ['text' => '➕ Add to Group ➕', 'url' => "https://t.me/{$botUsername}?startgroup=botstart"]
                ]
            ];
            
            $botApi->sendMessage($chatId, $message, $keyboard);
        }
        // Handle /reactions command
        elseif (isset($data['message']) && $text === '/reactions') {
            $reactionsText = implode(', ', $reactions);
            $botApi->sendMessage($chatId, "✅ Enabled Reactions : \n\n" . $reactionsText . "\n\n*Developed by @VenomDevX*");
        }
        // Handle regular messages and reactions
        else {
            // Calculate threshold: higher RandomLevel, lower threshold
            $threshold = 1 - ($randomLevel / 10);
            
            if (!in_array($chatId, $restrictedChats)) {
                // Check if chat is a group or supergroup to determine if reactions should be random
                if (in_array($content['chat']['type'], ['group', 'supergroup'])) {
                    // Run Function Randomly - According to the RANDOM_LEVEL
                    if (mt_rand() / mt_getrandmax() <= $threshold) {
                        $botApi->setMessageReaction($chatId, $messageId, getRandomPositiveReaction($reactions));
                    }
                } else {
                    // For non-group chats, set the reaction directly
                    $botApi->setMessageReaction($chatId, $messageId, getRandomPositiveReaction($reactions));
                }
            }
        }
    }
}

/**
 * Main execution
 */
try {
    // Parse reactions and restricted chats
    $reactions = splitEmojis($EMOJI_LIST);
    $restrictedChats = getChatIds($RESTRICTED_CHATS);
    
    // Initialize bot API
    $botApi = new TelegramBotAPI($BOT_TOKEN);
    
    // Handle different request methods
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data) {
            onUpdate($data, $botApi, $reactions, $restrictedChats, $BOT_USERNAME, $RANDOM_LEVEL);
        }
        
        // Return HTTP 200 OK to Telegram
        http_response_code(200);
        echo 'Ok';
    }
    // Health check endpoint
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['health'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'environment' => getenv('NODE_ENV') ?: 'production',
            'botConfigured' => !empty($BOT_TOKEN) && !empty($BOT_USERNAME),
            'developer' => '@VenomDevX'
        ]);
    }
    // Default GET request - show HTML page
    else {
        header('Content-Type: text/html');
        echo HTML_CONTENT;
    }
    
} catch (Exception $e) {
    error_log('Error in main execution: ' . $e->getMessage());
    
    // Always return 200 to Telegram to avoid retries
    http_response_code(200);
    echo 'Ok';
}
?>
