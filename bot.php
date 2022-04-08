<?php


$token = '5096078242:AAF53t5nVIZHflKtDv2gAKZpMtTp4p0ouwY';
define('API_KEY', $token);


#Recieve Updates
$update = json_decode(file_get_contents("php://input"));
if (isset($update->message)) {
    $msg                    = $update->message;
    $from_id                = $msg->from->id;
    $chat_id                = $msg->chat->id;
    $text                   = $msg->text;
    $first_name             = $msg->from->first_name;
    $message_id             = $msg->message_id;
    $chat_type              = $msg->message->chat->type;
} elseif (isset($update->channel_post)) {
    $channel_post             = $update->channel_post;
    $channel_post_msg_id      = $channel_post->message_id;
    $channel_post_sender_chat = $channel_post->sender_chat->id;
} elseif (isset($update->chat_member)) {
    $ChatMember = $update->chat_member;
}
$DB = DB();

$bot = bot('getMe')->result->username;
#DATABASE
if (!file_exists('DB.json')) {
    $base = [
        'off'       => false,
        'admins'    => [0 => 1755339772],
    ];
    file_put_contents("DB.json", json_encode($base, 128 | 256));
}

if (isset($channel_post) && $DB['off'] == false) {
    $name    = $channel_post->author_signature;
    $time = time();
    if ($DB['spam'][$name]['ban'] != true) {
        if (!isset($DB['spam'][$name]['last'])) {
            $DB['spam'][$name]['last'] = $time;
        }
        if ($time - $DB['spam'][$name]['last'] <= 1) {
            $DB['spam'][$name]['last'] = $time;
            $DB['spam'][$name]['try'] += 1;
        } else {
            $DB['spam'][$name]['last'] = $time;
            $DB['spam'][$name]['try'] = 0;
        }
        if ($DB['spam'][$name]['try'] >= 2) {
            $DB['spam'][$name]['ban'] = true;
        }
    } else {
        bot('deletemessage', [
            'chat_id'    => $channel_post->sender_chat->id,
            'message_id' => $channel_post->message_id
        ]);
    }

} elseif (isset($ChatMember) && $DB['off'] == false) {
    $admin  = $ChatMember->from->id;
    $status = $ChatMember->new_chat_member->status;
    if ($status == 'kicked') {
        if ($DB['banlimit'][$admin]) {
            $DB['banlimit'][$admin] += 1;
        } else {
            $DB['banlimit'][$admin] = 1;
        }
        if ($DB['banlimit'][$admin] >= 2) {
            $result = bot('promoteChatMember', [
                "chat_id"                => $ChatMember->chat->id,
                "user_id"                => $admin,
                "can_post_messages"      => false,
            ]);
            $DB['banlimit'][$admin] = 0;
        }
    }
    DB($DB);
}


function bot($method, $data = [])
{
    $ch = curl_init("https://api.telegram.org/bot" . API_KEY . "/" . $method);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_POSTFIELDS => $data]);
    if (!curl_error($ch)) {
        return json_decode(curl_exec($ch));
    } else {
        var_dump(curl_exec($ch));
    }
}
#Function (SendMessage)
function sm($chat_id, $text, $key, $msg_id)
{
    bot('sendmessage', [
        'chat_id'             => $chat_id,
        'text'                => $text,
        'parse_mode'          => "markdown",
        'reply_markup'        => $key,
        'reply_to_message_id' => $msg_id
    ]);
}


function DB($INP = null)
{
    if ($INP == null) {
        return json_decode(file_get_contents("DB.json"), true);
    } else {
        file_put_contents("DB.json", json_encode($INP, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return;
    }
}

if ($from_id == $DB['admins'][0]) {
    if ($text == 'off') {
        $DB['off'] = true;
        bot('sendmessage', [
            'chat_id'             => $chat_id,
            'text'                => 'Detection Now Is off.',
        ]);
        DB($DB);
    } elseif ($text == 'on') {
        $DB['off'] = false;
        bot('sendmessage', [
            'chat_id'             => $chat_id,
            'text'                => 'Detection Now Is on.',
        ]);
        DB($DB);
    }
    if (preg_match('/^[\/\!\.\#]?setadmin (.*) (.*)$/', $text, $out)) {
        try {
            $result = bot('promoteChatMember', [
                "chat_id"                => $out[2],
                "user_id"                => $out[1],
                "can_manage_chat"        => true,
                "can_change_info"        => true,
                "can_post_messages"      => true,
                "can_edit_messages"      => true,
                "can_delete_messages"    => true,
                "can_invite_users"       => true,
                "can_restrict_members"   => true,
                "can_manage_voice_chats" => true,
                "is_anonymous"           => false
            ]);
            $result = json_encode($result, 128);
            bot('sendMessage', [
                'chat_id'    => $DB['admins'][0],
                'text'       => "<pre>$result</pre>",
                'parse_mode' => 'html'
            ]);
            
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            bot('sendMessage', [
                'chat_id'    => $DB['admins'][0],
                'text'       => "<pre>$error</pre>",
                'parse_mode' => 'html'
            ]);
        }
    } elseif (preg_match('/^[\/\!\.\#]?deladmin (.*) (.*)$/', $text, $o)) {
        try {
            $result = bot('promoteChatMember', [
                "chat_id"                => $o[2],
                "user_id"                => $o[1],
                "can_post_messages"      => false,
            ]);
            $result = json_encode($result, 128);
            bot('sendMessage', [
                'chat_id'    => $DB['admins'][0],
                'text'       => "<pre>$result</pre>",
                'parse_mode' => 'html'
            ]);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            bot('sendMessage', [
                'chat_id'    => $DB['admins'][0],
                'text'       => "<pre>$error</pre>",
                'parse_mode' => 'html'
            ]);
        }
    }
}
