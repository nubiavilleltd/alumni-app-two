<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Chat_api extends MY_Controller {

    public function __construct()
    {
        
        parent::__construct();

        $this->load->model('Chat_model');
        $this->load->model('api_model');
        $this->load->model('base_model');
        $this->load->helper(['file', 'form']);
        $this->load->library(['email', 'ion_auth', 'jwt_helper']);
    }

    // =========================================================================
    // THREADS
    // =========================================================================

    /**
     * GET THREADS
     * Returns all DM + group threads for the logged-in user.
     *
     * Headers: Authorization: Bearer {access_token}
     * Body:    { "token": "...", "jwt": "..." (optional fallback) }
     */
    public function get_threads()
    {
        $obj = $this->get_request_body();
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }        

        $user_id = (int)$jwtData->user_id;

        $threads = $this->Chat_model->get_threads($user_id);
        return $this->respond(200, 'OK', ['threads' => $threads]);
    }

    /**
     * GET THREAD
     * Returns one thread's messages + participants. Also marks as read.
     *
     * Body: { token, jwt (optional), group_id, limit (opt), offset (opt) }
     */
    public function get_thread()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        } 

        $user_id  = (int)$jwtData->user_id;
        $group_id = (int)($obj['group_id'] ?? 0);
        $limit    = (int)($obj['limit']    ?? 50);
        $offset   = (int)($obj['offset']   ?? 0);

        if (!$group_id) {
            return $this->respond(400, 'group_id is required');
        }

        if (!$this->Chat_model->is_member($group_id, $user_id)) {
            return $this->respond(403, 'You are not a member of this thread');
        }

        $thread = $this->Chat_model->get_thread($group_id, $user_id, $limit, $offset);
        if (!$thread) {
            return $this->respond(404, 'Thread not found');
        }

        return $this->respond(200, 'OK', ['thread' => $thread]);
    }

    /**
     * CREATE GROUP CHAT
     *
     * Body: { token, jwt (opt), name, member_ids (array), description (opt), is_private (opt), chapter_id (opt), year (opt) }
     */
    public function create_group()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        } 

        $created_by = (int)$jwtData->user_id;
        $name       = trim($obj['name']       ?? '');
        $member_ids = $obj['member_ids']       ?? [];

        if (!$name || empty($member_ids)) {
            return $this->respond(400, 'name and member_ids are required');
        }

        if (!in_array($created_by, $member_ids)) {
            $member_ids[] = $created_by;
        }

        $extra = [];
        if (!empty($obj['description']))   $extra['description'] = $obj['description'];
        if (isset($obj['is_private']))     $extra['is_private']  = (int)$obj['is_private'];
        if (!empty($obj['chapter_id']))    $extra['chapter_id']  = (int)$obj['chapter_id'];
        if (!empty($obj['year']))          $extra['year']        = (int)$obj['year'];

        $group_id = $this->Chat_model->create_thread($name, 'group', $created_by, $member_ids, $extra);

        return $this->respond(200, 'Group created', ['group_id' => $group_id]);
    }

    // =========================================================================
    // MESSAGES
    // =========================================================================

    /**
     * SEND MESSAGE to an existing thread (text and/or file)
     *
     * Body (JSON):      { token, jwt (opt), group_id, message }
     * Body (multipart): { token, jwt (opt), group_id, message (opt), file }
     */
    public function send_message()
    {
        $obj = $this->get_request_body();
        log_message('error', 'Chat send_message: ' . var_export($obj, true));

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        } 

        $user_id     = (int)$jwtData->user_id;
        $sender_name = $jwtData->fullname ?? 'user';
        $group_id    = (int)($obj['group_id'] ?? 0);
        $message     = trim($obj['message']   ?? '');

        if (!$group_id) {
            return $this->respond(400, 'group_id is required');
        }

        if (!$this->Chat_model->is_member($group_id, $user_id)) {
            return $this->respond(403, 'You are not a member of this group');
        }

        $attachment      = null;
        $attachment_type = $obj['attachment_type'] ?? null;

        if (!empty($_FILES['file']['name'])) {
            $upload = $this->upload_user_media('file', $user_id, $sender_name);
            if ($upload['status'] !== 200) {
                return $this->respond(400, $upload['error']);
            }
            $attachment      = $upload['file_url'];
            $attachment_type = $attachment_type ?: $this->detect_attachment_type($_FILES['file']['name']);
        }

        if (!$message && !$attachment) {
            return $this->respond(400, 'message or file is required');
        }

        $message_id = $this->Chat_model->insert_message([
            'group_id'        => $group_id,
            'user_id'         => $user_id,
            'message'         => $message ?: null,
            'attachment'      => $attachment,
            'attachment_type' => $attachment_type,
            'is_deleted'      => 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->Chat_model->mark_thread_read($group_id, $user_id);

        $members = $this->db->select('user_id')
            ->where('group_id', $group_id)
            ->where('user_id !=', $user_id)
            ->get('chat_group_members')->result_array();
        $preview = $message ?: '📎 Attachment';
        $this->notify_participants(array_column($members, 'user_id'), $sender_name, $preview, $group_id);

        return $this->respond(200, 'Message sent', [
            'message' => $this->Chat_model->get_message($message_id),
        ]);
    }

    /**
     * SEND DIRECT MESSAGE
     * Auto-creates a DM thread if one doesn't exist, then sends.
     *
     * Body: { token, jwt (opt), recipient_id, message }
     * File: optional multipart file
     */
    public function send_direct()
    {
        $obj = $this->get_request_body();
        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $sender_id    = (int)$jwtData->user_id;
        $sender_name  = $jwtData->fullname ?? 'user';
        $recipient_id = (int)($obj['recipient_id'] ?? 0);
        $message      = trim($obj['message']       ?? '');

        if (!$recipient_id) {
            return $this->respond(400, 'recipient_id is required');
        }
        if ($sender_id === $recipient_id) {
            return $this->respond(400, 'Cannot send a DM to yourself');
        }

        $attachment      = null;
        $attachment_type = $obj['attachment_type'] ?? null;

        if (!empty($_FILES['file']['name'])) {
            $upload = $this->upload_user_media('file', $sender_id, $sender_name);
            if ($upload['status'] !== 200) {
                return $this->respond(400, $upload['error']);
            }
            $attachment      = $upload['file_url'];
            $attachment_type = $attachment_type ?: $this->detect_attachment_type($_FILES['file']['name']);
        }

        if (!$message && !$attachment) {
            return $this->respond(400, 'message or file is required');
        }

        // Find existing DM thread or create new one
        $group_id = $this->Chat_model->find_direct_thread($sender_id, $recipient_id);

        if (!$group_id) {
            $group_id = $this->Chat_model->create_thread(
                null,
                'direct',
                $sender_id,
                [$sender_id, $recipient_id]
            );
        }

        $message_id = $this->Chat_model->insert_message([
            'group_id'        => $group_id,
            'user_id'         => $sender_id,
            'message'         => $message ?: null,
            'attachment'      => $attachment,
            'attachment_type' => $attachment_type,
            'is_deleted'      => 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->Chat_model->mark_thread_read($group_id, $sender_id);

        $preview = $message ?: '📎 Attachment';
        $this->notify_participants([$recipient_id], $sender_name, $preview, $group_id);

        return $this->respond(200, 'Message sent', [
            'group_id' => $group_id,
            'message'  => $this->Chat_model->get_message($message_id),
        ]);
    }

    /**
     * LIST MESSAGES (paginated)
     *
     * Body: { token, jwt (opt), group_id, limit (opt), offset (opt) }
     */
    public function list_messages()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id  = (int)$jwtData->user_id;
        $group_id = (int)($obj['group_id'] ?? 0);
        $limit    = (int)($obj['limit']    ?? 50);
        $offset   = (int)($obj['offset']   ?? 0);

        if (!$group_id) {
            return $this->respond(400, 'group_id is required');
        }

        $this->db->select('cm.*, u.fullname AS sender_name, u.image AS sender_avatar')
            ->from('chat_messages cm')
            ->join('users u', 'u.id = cm.user_id', 'left')
            ->where('cm.group_id', $group_id)
            ->order_by('cm.created_at', 'DESC')
            ->limit($limit, $offset);

        $rows = $this->db->get()->result_array();

        foreach ($rows as &$msg) {
            if (!empty($msg['sender_avatar'])) {
                $msg['sender_avatar'] = site_url($msg['sender_avatar']);
            }
        }

        $this->Chat_model->mark_thread_read($group_id, $user_id);

        return $this->respond(200, 'OK', ['messages' => array_reverse($rows)]);
    }

    /**
     * DELETE MESSAGE (soft delete — clears content, sets is_deleted = 1)
     *
     * Body: { token, jwt (opt), message_id }
     */
    public function delete_message()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id    = (int)$jwtData->user_id;
        $message_id = (int)($obj['message_id'] ?? 0);

        if (!$message_id) {
            return $this->respond(400, 'message_id is required');
        }

        $deleted = $this->Chat_model->soft_delete_message($message_id, $user_id);

        if (!$deleted) {
            return $this->respond(404, 'Message not found or you are not the sender');
        }

        return $this->respond(200, 'Message deleted', [
            'message_id' => $message_id,
            'is_deleted' => true,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // MARK READ
    // =========================================================================

    /**
     * MARK THREAD READ
     *
     * Body: { token, jwt (opt), group_id }
     */
    public function mark_read()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id  = (int)$jwtData->user_id;
        $group_id = (int)($obj['group_id'] ?? 0);

        if (!$group_id) {
            return $this->respond(400, 'group_id is required');
        }

        $this->Chat_model->mark_thread_read($group_id, $user_id);

        return $this->respond(200, 'Marked as read');
    }

    // =========================================================================
    // MEMBERSHIP
    // =========================================================================

    /**
     * ADD MEMBER to a group chat (admin action)
     *
     * Body: { token, jwt (opt), group_id, user_id }
     */
    public function add_member()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $group_id      = (int)($obj['group_id'] ?? 0);
        $new_member_id = (int)($obj['user_id']  ?? 0);

        if (!$group_id || !$new_member_id) {
            return $this->respond(400, 'group_id and user_id are required');
        }

        $added = $this->Chat_model->add_member($group_id, $new_member_id);

        return $added
            ? $this->respond(200, 'Member added')
            : $this->respond(409, 'User is already a member');
    }

    /**
     * LEAVE GROUP
     *
     * Body: { token, jwt (opt), group_id }
     */
    public function leave_group()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id  = (int)$jwtData->user_id;
        $group_id = (int)($obj['group_id'] ?? 0);

        if (!$group_id) {
            return $this->respond(400, 'group_id is required');
        }

        $this->Chat_model->remove_member($group_id, $user_id);

        return $this->respond(200, 'Left group');
    }

    /**
     * PIN / UNPIN a thread for the logged-in user
     *
     * Body: { token, jwt (opt), group_id, pin (1 or 0) }
     */
    public function pin_thread()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id  = (int)$jwtData->user_id;
        $group_id = (int)($obj['group_id'] ?? 0);
        $pin      = isset($obj['pin']) ? (bool)$obj['pin'] : true;

        if (!$group_id) {
            return $this->respond(400, 'group_id is required');
        }

        $this->Chat_model->pin_thread($group_id, $user_id, $pin);

        return $this->respond(200, $pin ? 'Thread pinned' : 'Thread unpinned');
    }

    // =========================================================================
    // MEDIA UPLOAD (standalone)
    // =========================================================================

    /**
     * UPLOAD MEDIA — uploads file and auto-creates a message row.
     *
     * Multipart body: { token, jwt (opt), group_id, file }
     */
    public function upload_media()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id     = (int)$jwtData->user_id;
        $sender_name = $jwtData->fullname ?? 'user';
        $group_id    = (int)($obj['group_id'] ?? 0);

        if (empty($_FILES['file']['name'])) {
            return $this->respond(400, 'file is required');
        }

        $upload = $this->upload_user_media('file', $user_id, $sender_name);
        if ($upload['status'] !== 200) {
            return $this->respond(400, $upload['error']);
        }

        $attachment_type = $this->detect_attachment_type($_FILES['file']['name']);
        $message_id      = null;

        if ($group_id) {
            $message_id = $this->Chat_model->insert_message([
                'group_id'        => $group_id,
                'user_id'         => $user_id,
                'message'         => null,
                'attachment'      => $upload['file_url'],
                'attachment_type' => $attachment_type,
                'is_deleted'      => 0,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
            $this->Chat_model->mark_thread_read($group_id, $user_id);
        }

        return $this->respond(200, 'Uploaded', [
            'file_url'        => $upload['file_url'],
            'attachment_type' => $attachment_type,
            'message_id'      => $message_id,
        ]);
    }

    // =========================================================================
    // PUSH NOTIFICATIONS
    // =========================================================================

    /**
     * GET VAPID PUBLIC KEY — Frontend calls this once on app load.
     * No JWT required — public key is not sensitive.
     */
    public function get_vapid_key()
    {
        if (!$this->checkAPI_token_from_header()) return $this->respond(403, 'Invalid API token');

        return $this->respond(200, 'OK', ['public_key' => VAPID_PUBLIC_KEY]);
    }

    /**
     * REGISTER PUSH SUBSCRIPTION
     * Frontend calls this after the user grants notification permission.
     *
     * Body: { token, jwt, endpoint, p256dh, auth, browser (opt) }
     */
    public function register_push_subscription()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id  = (int)$jwtData->user_id;
        $endpoint = trim($obj['endpoint'] ?? '');
        $p256dh   = trim($obj['p256dh']   ?? '');
        $auth     = trim($obj['auth']     ?? '');
        $browser  = trim($obj['browser']  ?? '');

        if (!$endpoint || !$p256dh || !$auth) {
            return $this->respond(400, 'endpoint, p256dh and auth are required');
        }

        $existing = $this->db
            ->where('user_id', $user_id)
            ->where('endpoint', $endpoint)
            ->get('user_push_subscriptions')
            ->row();

        $data = [
            'user_id'    => $user_id,
            'endpoint'   => $endpoint,
            'p256dh'     => $p256dh,
            'auth'       => $auth,
            'browser'    => $browser ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $this->db->where('id', $existing->id)->update('user_push_subscriptions', $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('user_push_subscriptions', $data);
        }

        return $this->respond(200, 'Subscription saved');
    }    

    // =========================================================================
    // V2 ENDPOINTS — New Messaging Schema
    // Tables: message_threads, thread_participants, messages, messages_attachments
    // All endpoints: POST, JWT required, token required
    // =========================================================================

    /**
     * V2 GET THREADS — Inbox
     *
     * Body: { token, jwt }
     */
    public function v2_get_threads()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id = (int)$jwtData->user_id;

        $threads = $this->Chat_model->v2_get_threads($user_id);
        return $this->respond(200, 'OK', ['threads' => $threads]);
    }

    /**
     * V2 GET THREAD — Open one conversation (marks delivered + read)
     *
     * Body: { token, jwt, thread_id, limit (opt), offset (opt) }
     */
    public function v2_get_thread()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id   = (int)$jwtData->user_id;
        $thread_id = (int)($obj['thread_id'] ?? 0);
        $limit     = (int)($obj['limit']     ?? 50);
        $offset    = (int)($obj['offset']    ?? 0);

        if (!$thread_id) return $this->respond(400, 'thread_id is required');

        if (!$this->Chat_model->v2_is_participant($thread_id, $user_id)) {
            return $this->respond(403, 'You are not a participant of this thread');
        }

        $thread = $this->Chat_model->v2_get_thread($thread_id, $user_id, $limit, $offset);
        if (!$thread) return $this->respond(404, 'Thread not found');

        return $this->respond(200, 'OK', ['thread' => $thread]);
    }

    /**
     * V2 CREATE GROUP — Creates a group thread immediately.
     * DMs are never created here — they are created on first send.
     *
     * Body: { token, jwt, title, member_ids, category (opt), attachment_enabled (opt), audio_enabled (opt) }
     */
    public function v2_create_group()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $created_by = (int)$jwtData->user_id;
        $title      = trim($obj['title']      ?? '');
        $member_ids = $obj['member_ids']       ?? [];

        if (!$title || empty($member_ids)) {
            return $this->respond(400, 'title and member_ids are required');
        }

        if (!in_array($created_by, $member_ids)) {
            $member_ids[] = $created_by;
        }

        $data = [
            'type'                => 'group',
            'title'               => $title,
            'created_by'          => $created_by,
            'category'            => $obj['category']           ?? 'community',
            'attachment_enabled'  => isset($obj['attachment_enabled']) ? (int)$obj['attachment_enabled'] : 1,
            'audio_enabled'       => isset($obj['audio_enabled'])      ? (int)$obj['audio_enabled']      : 0,
        ];

        $thread_id = $this->Chat_model->v2_create_thread($data, $member_ids);
        return $this->respond(200, 'Group created', ['thread_id' => $thread_id]);
    }

    /**
     * V2 SEND MESSAGE — Send to an existing thread.
     * Supports dedup via client_generated_id.
     * Attach previously uploaded attachments via attachment_ids array.
     *
     * Body: { token, jwt, thread_id, body (opt), client_generated_id (opt),
     *         reply_to_message_id (opt), attachment_ids (opt array) }
     */
    public function v2_send_message()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id   = (int)$jwtData->user_id;
        $thread_id = (int)($obj['thread_id'] ?? 0);
        $body      = trim($obj['body']       ?? '');

        if (!$thread_id) return $this->respond(400, 'thread_id is required');

        if (!$this->Chat_model->v2_is_participant($thread_id, $user_id)) {
            return $this->respond(403, 'You are not a participant of this thread');
        }

        $attachment_ids = $obj['attachment_ids'] ?? [];

        if (!$body && empty($attachment_ids)) {
            return $this->respond(400, 'body or attachment_ids is required');
        }

        // Determine message_type
        $message_type = 'text';
        if ($body && !empty($attachment_ids)) $message_type = 'mixed';
        elseif (!empty($attachment_ids))      $message_type = 'file';

        $message_id = $this->Chat_model->v2_insert_message([
            'thread_id'            => $thread_id,
            'sender_member_id'     => $user_id,
            'body'                 => $body ?: null,
            'message_type'         => $message_type,
            'reply_to_message_id'  => !empty($obj['reply_to_message_id']) ? (int)$obj['reply_to_message_id'] : null,
            'client_generated_id'  => $obj['client_generated_id'] ?? null,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        // Link any staged attachments to this message
        foreach ($attachment_ids as $att_id) {
            $this->Chat_model->v2_link_attachment((int)$att_id, $message_id);
        }

        // Sender has already read their own message
        $this->Chat_model->v2_mark_read($thread_id, $user_id, $message_id);

        $participants = $this->db->select('member_id as user_id')
            ->where('thread_id', $thread_id)
            ->where('member_id !=', $user_id)
            ->where('left_at IS NULL', null, false)
            ->get('thread_participants')->result_array();
        $preview = $body ?: '📎 Attachment';
        $this->notify_participants(array_column($participants, 'user_id'), $jwtData->fullname ?? 'New message', $preview, $thread_id);

        return $this->respond(200, 'Message sent', [
            'message' => $this->Chat_model->v2_get_message($message_id),
        ]);
    }

    /**
     * V2 SEND DIRECT — First DM or reply to existing DM.
     * Creates thread + participants atomically on first send.
     * Subsequent sends reuse the same thread via direct_key.
     *
     * Body: { token, jwt, recipient_id, body (opt), client_generated_id (opt),
     *         reply_to_message_id (opt), attachment_ids (opt array) }
     */
    public function v2_send_direct()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $sender_id    = (int)$jwtData->user_id;
        $recipient_id = (int)($obj['recipient_id'] ?? 0);
        $body         = trim($obj['body'] ?? '');

        if (!$recipient_id)              return $this->respond(400, 'recipient_id is required');
        if ($sender_id === $recipient_id) return $this->respond(400, 'Cannot send a DM to yourself');

        $attachment_ids = $obj['attachment_ids'] ?? [];

        if (!$body && empty($attachment_ids)) {
            return $this->respond(400, 'body or attachment_ids is required');
        }

        // Find or create the DM thread (only on first actual send)
        $direct_key = $this->Chat_model->v2_build_direct_key($sender_id, $recipient_id);
        $thread_id  = $this->Chat_model->v2_find_direct_thread($direct_key);

        $thread_created = false;
        if (!$thread_id) {
            $thread_id = $this->Chat_model->v2_create_thread([
                'type'       => 'direct',
                'created_by' => $sender_id,
                'direct_key' => $direct_key,
                'category'   => 'community',
            ], [$sender_id, $recipient_id]);
            $thread_created = true;
        }

        $message_type = 'text';
        if ($body && !empty($attachment_ids)) $message_type = 'mixed';
        elseif (!empty($attachment_ids))      $message_type = 'file';

        $message_id = $this->Chat_model->v2_insert_message([
            'thread_id'           => $thread_id,
            'sender_member_id'    => $sender_id,
            'body'                => $body ?: null,
            'message_type'        => $message_type,
            'reply_to_message_id' => !empty($obj['reply_to_message_id']) ? (int)$obj['reply_to_message_id'] : null,
            'client_generated_id' => $obj['client_generated_id'] ?? null,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        foreach ($attachment_ids as $att_id) {
            $this->Chat_model->v2_link_attachment((int)$att_id, $message_id);
        }

        $this->Chat_model->v2_mark_read($thread_id, $sender_id, $message_id);

        $preview = $body ?: '📎 Attachment';
        $this->notify_participants([$recipient_id], $jwtData->fullname ?? 'New message', $preview, $thread_id);

        return $this->respond(200, 'Message sent', [
            'thread_id'      => $thread_id,
            'thread_created' => $thread_created,
            'message'        => $this->Chat_model->v2_get_message($message_id),
        ]);
    }

    /**
     * V2 UPLOAD ATTACHMENT — Stage a file without creating a message.
     * Returns attachment_id to pass in attachment_ids when sending.
     *
     * Multipart body: { token, jwt, thread_id, file }
     */
    public function v2_upload_attachment()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id      = (int)$jwtData->user_id;
        $sender_name  = $jwtData->fullname ?? 'user';
        $thread_id    = (int)($obj['thread_id']    ?? 0);
        $recipient_id = (int)($obj['recipient_id'] ?? 0);

        if (!$thread_id && !$recipient_id) {
            return $this->respond(400, 'thread_id or recipient_id is required');
        }
        if (empty($_FILES['file']['name'])) return $this->respond(400, 'file is required');

        // If no thread_id, resolve or create the DM thread from recipient_id
        if (!$thread_id) {
            if ($user_id === $recipient_id) {
                return $this->respond(400, 'Cannot send a DM to yourself');
            }
            $direct_key = $this->Chat_model->v2_build_direct_key($user_id, $recipient_id);
            $thread_id  = $this->Chat_model->v2_find_direct_thread($direct_key);
            if (!$thread_id) {
                $thread_id = $this->Chat_model->v2_create_thread([
                    'type'       => 'direct',
                    'created_by' => $user_id,
                    'direct_key' => $direct_key,
                    'category'   => 'community',
                ], [$user_id, $recipient_id]);
            }
        }

        if (!$this->Chat_model->v2_is_participant($thread_id, $user_id)) {
            return $this->respond(403, 'You are not a participant of this thread');
        }

        $upload = $this->upload_user_media('file', $user_id, $sender_name);
        if ($upload['status'] !== 200) return $this->respond(400, $upload['error']);

        $ext  = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $kind = $this->v2_detect_kind($ext);

        $attachment_id = $this->Chat_model->v2_insert_attachment([
            'thread_id'     => $thread_id,
            'message_id'    => null, // linked when message is sent
            'kind'          => $kind,
            'file_name'     => $_FILES['file']['name'],
            'mime_type'     => $_FILES['file']['type'] ?? null,
            'size_in_bytes' => $_FILES['file']['size'] ?? null,
            'storage_path'  => $upload['file_path'],
            'public_url'    => $upload['file_url'],
        ]);

        return $this->respond(200, 'Attachment staged', [
            'attachment_id' => $attachment_id,
            'thread_id'     => $thread_id,
            'public_url'    => $upload['file_url'],
            'kind'          => $kind,
        ]);
    }

    /**
     * V2 DELETE MESSAGE — Soft delete (body cleared, deleted_at set).
     * Only the sender can delete their own message.
     *
     * Body: { token, jwt, message_id }
     */
    public function v2_delete_message()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id    = (int)$jwtData->user_id;
        $message_id = (int)($obj['message_id'] ?? 0);

        if (!$message_id) return $this->respond(400, 'message_id is required');

        $deleted = $this->Chat_model->v2_delete_message($message_id, $user_id);
        if (!$deleted) return $this->respond(404, 'Message not found or you are not the sender');

        return $this->respond(200, 'Message removed', [
            'message_id' => $message_id,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * V2 MARK READ — Advance last_read_message_id cursor (Seen state).
     *
     * Body: { token, jwt, thread_id, message_id }
     */
    public function v2_mark_read()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id    = (int)$jwtData->user_id;
        $thread_id  = (int)($obj['thread_id']  ?? 0);
        $message_id = (int)($obj['message_id'] ?? 0);

        if (!$thread_id) return $this->respond(400, 'thread_id is required');

        // Default to latest visible message when no message_id supplied
        if (!$message_id) {
            $latest = $this->db
                ->select('id')
                ->where('thread_id', $thread_id)
                ->where('deleted_at IS NULL', null, false)
                ->order_by('id', 'DESC')
                ->limit(1)
                ->get('messages')->row();
            if (!$latest) return $this->respond(200, 'No messages', ['thread_id' => $thread_id, 'unread_count' => 0, 'server_time' => date('c')]);
            $message_id = (int)$latest->id;
        }

        if (!$this->Chat_model->v2_is_participant($thread_id, $user_id)) {
            return $this->respond(403, 'You are not a participant of this thread');
        }

        $this->Chat_model->v2_mark_read($thread_id, $user_id, $message_id);

        $participant = $this->db->get_where('thread_participants', [
            'thread_id' => $thread_id,
            'member_id' => $user_id,
        ])->row();
        $unread_count = $this->Chat_model->v2_get_unread_count($thread_id, $user_id, $participant->last_read_message_id ?? null);

        return $this->respond(200, 'Marked as read', [
            'thread_id'   => $thread_id,
            'unread_count' => $unread_count,
            'server_time' => date('c'),
        ]);
    }

    /**
     * V2 MARK DELIVERED — Advance last_delivered_message_id cursor.
     * Call when the client receives new messages (inbox load or push).
     *
     * Body: { token, jwt, thread_id, message_id }
     */
    public function v2_mark_delivered()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id    = (int)$jwtData->user_id;
        $thread_id  = (int)($obj['thread_id']  ?? 0);
        $message_id = (int)($obj['message_id'] ?? 0);

        if (!$thread_id || !$message_id) return $this->respond(400, 'thread_id and message_id are required');

        $this->Chat_model->v2_mark_delivered($thread_id, $user_id, $message_id);
        return $this->respond(200, 'Marked as delivered');
    }

    /**
     * V2 ADD MEMBER — Add a user to a group thread.
     *
     * Body: { token, jwt, thread_id, user_id }
     */
    public function v2_add_member()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $thread_id     = (int)($obj['thread_id'] ?? 0);
        $new_member_id = (int)($obj['user_id']   ?? 0);

        if (!$thread_id || !$new_member_id) return $this->respond(400, 'thread_id and user_id are required');

        $added = $this->Chat_model->v2_add_participant($thread_id, $new_member_id);
        return $added
            ? $this->respond(200, 'Member added')
            : $this->respond(409, 'User is already an active member');
    }

    /**
     * V2 LEAVE GROUP — Soft-leave (sets left_at, row stays for history).
     *
     * Body: { token, jwt, thread_id }
     */
    public function v2_leave_group()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id   = (int)$jwtData->user_id;
        $thread_id = (int)($obj['thread_id'] ?? 0);

        if (!$thread_id) return $this->respond(400, 'thread_id is required');

        $this->Chat_model->v2_leave_thread($thread_id, $user_id);
        return $this->respond(200, 'Left thread');
    }

    /**
     * V2 PIN THREAD — Pin or unpin a thread for the logged-in user only.
     *
     * Body: { token, jwt, thread_id, pin (1 or 0) }
     */
    public function v2_pin_thread()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id   = (int)$jwtData->user_id;
        $thread_id = (int)($obj['thread_id'] ?? 0);
        $pin       = isset($obj['pin']) ? (bool)$obj['pin'] : true;

        if (!$thread_id) return $this->respond(400, 'thread_id is required');

        $this->Chat_model->v2_pin_thread($thread_id, $user_id, $pin);
        return $this->respond(200, $pin ? 'Thread pinned' : 'Thread unpinned');
    }

    /**
     * V2 SYNC YEAR GROUPS — Trigger graduation-year group sync.
     *
     * With no body params: syncs only the calling user (fast, safe for client use).
     * With sync_all=1 param: syncs all users (admin-only, run once after migration).
     *
     * Body: { token, jwt, sync_all (opt, 1 = all users) }
     */
    public function v2_sync_year_groups()
    {
        $obj = $this->get_request_body();

        $jwtData = $this->authorize();

        if (!$jwtData) {
            return;
        }

        $user_id  = (int)$jwtData->user_id;
        $sync_all = !empty($obj['sync_all']);

        if ($sync_all) {
            $this->Chat_model->v2_sync_all_year_groups();
            return $this->respond(200, 'All users synced to their graduation-year groups');
        }

        $this->Chat_model->v2_sync_user_year_group($user_id);
        return $this->respond(200, 'Synced to graduation-year group');
    }

    // =========================================================================
    // V2 HELPERS
    // =========================================================================

    /**
     * Map file extension to attachment kind (image / audio / file).
     */
    protected function v2_detect_kind($ext)
    {
        if (in_array($ext, ['jpg','jpeg','png','gif','webp']))      return 'image';
        if (in_array($ext, ['mp3','wav','m4a','ogg','webm','3gp'])) return 'audio';
        return 'file';
    }

    // =========================================================================
    // PUSH NOTIFICATION HELPER (private)
    // =========================================================================

    /**
     * Fire Web Push notifications to a list of users.
     *
     * @param array  $user_ids     Recipients (sender excluded before calling)
     * @param string $title        Notification title — typically the sender's name
     * @param string $body         Message preview text
     * @param int    $thread_id    Used to build the deep-link URL on click
     * @param string|null $icon    Optional icon URL; falls back to /logo.png
     */
    private function notify_participants($user_ids, $title, $body, $thread_id, $icon = null)
    {
        if (empty($user_ids)) return;

        require_once FCPATH . 'vendor/autoload.php';

        $subscriptions = $this->db
            ->where_in('user_id', $user_ids)
            ->get('user_push_subscriptions')
            ->result_array();

        if (empty($subscriptions)) return;

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => VAPID_SUBJECT,
                'publicKey'  => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => strlen($body) > 100 ? substr($body, 0, 97) . '...' : $body,
            'url'   => '/messages?threadId=' . $thread_id,
            'icon'  => $icon ?? '/logo.png',
            'badge' => '/badge.png',
        ]);

        foreach ($subscriptions as $row) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'keys'     => [
                    'p256dh' => $row['p256dh'],
                    'auth'   => $row['auth'],
                ],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        $expired = [];
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $expired[] = $report->getRequest()->getUri()->__toString();
                log_message('error', 'Push notification failed: ' . $report->getReason());
            }
        }

        // Remove expired or invalid subscriptions automatically
        if (!empty($expired)) {
            $this->db->where_in('endpoint', $expired)->delete('user_push_subscriptions');
        }
    }

    // =========================================================================
    // HELPERS (protected)
    // =========================================================================

    protected function get_request_body()
    {
        $contentType = $this->input->server('CONTENT_TYPE') ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $this->input->post() ?? [];
    }

    protected function upload_user_media($fileKey, $user_id, $user_name)
    {
        $safeName   = preg_replace('/[^A-Za-z0-9_\-]/', '_', $user_name);
        $userFolder = './uploads/chat/' . $user_id . '_' . $safeName . '/';

        if (!is_dir($userFolder)) {
            mkdir($userFolder, 0777, true);
        }

        $config = [
            'upload_path'   => $userFolder,
            'allowed_types' => 'jpg|jpeg|png|gif|mp4|mp3|wav|m4a|3gp|pdf|doc|docx',
            'max_size'      => 56320,
            'encrypt_name'  => true,
        ];

        $this->load->library('upload', $config);

        if (!$this->upload->do_upload($fileKey)) {
            $error  = $this->upload->display_errors('', '');
            $sizeMB = number_format(($_FILES[$fileKey]['size'] ?? 0) / 1048576, 2);
            return ['status' => 400, 'error' => $error, 'file_size_mb' => $sizeMB];
        }

        $data = $this->upload->data();

        return [
            'status'       => 200,
            'file_url'     => base_url('uploads/chat/' . $user_id . '_' . $safeName . '/' . $data['file_name']),
            'file_path'    => $userFolder . $data['file_name'],
            'file_size_mb' => number_format($data['file_size'] / 1024, 2),
        ];
    }

    protected function detect_attachment_type($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'image'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video'    => ['mp4', 'mov', 'avi', '3gp'],
            'audio'    => ['mp3', 'wav', 'm4a', 'ogg'],
            'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        ];
        foreach ($map as $type => $exts) {
            if (in_array($ext, $exts)) return $type;
        }
        return 'file';
    }

    protected function respond($status, $message, $data = [])
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => $status,
                'message' => $message,
                'data'    => $data,
            ]));
    }
}
