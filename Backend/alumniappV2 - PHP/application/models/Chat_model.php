<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Chat_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
    }

    // =========================================================================
    // THREADS
    // =========================================================================

    /**
     * Get all threads (DM + group) the user belongs to.
     * Returns each thread with: participants, last message preview, unread count.
     * Ordered by most recently active first.
     */
    public function get_threads($user_id)
    {
        $this->db->select('
                cg.id          AS group_id,
                cg.name,
                cg.type,
                cg.description,
                cg.cover_image,
                cg.is_private,
                cg.created_at  AS group_created_at,
                cgm.role,
                cgm.last_read_at,
                cgm.is_pinned
            ')
            ->from('chat_groups cg')
            ->join('chat_group_members cgm', 'cgm.group_id = cg.id')
            ->where('cgm.user_id', $user_id)
            ->order_by('cgm.is_pinned', 'DESC');

        $threads = $this->db->get()->result_array();

        foreach ($threads as &$thread) {
            $gid = $thread['group_id'];

            // Last non-deleted message
            $last = $this->db
                ->select('cm.message, cm.attachment, cm.attachment_type, cm.created_at, u.fullname AS sender_name')
                ->from('chat_messages cm')
                ->join('users u', 'u.id = cm.user_id', 'left')
                ->where('cm.group_id', $gid)
                ->where('cm.is_deleted', 0)
                ->order_by('cm.created_at', 'DESC')
                ->limit(1)
                ->get()->row_array();

            $thread['last_message'] = $last ?: null;

            // Unread count: messages after last_read_at, not sent by this user
            $this->db->from('chat_messages')
                ->where('group_id', $gid)
                ->where('user_id !=', $user_id)
                ->where('is_deleted', 0);

            if (!empty($thread['last_read_at'])) {
                $this->db->where('created_at >', $thread['last_read_at']);
            }

            $thread['unread_count'] = $this->db->count_all_results();

            // Participants (lightweight — just user_id + name + avatar)
            $thread['participants'] = $this->get_thread_participants($gid);
        }

        // Sort: pinned first, then by last message time descending
        usort($threads, function ($a, $b) {
            if ($a['is_pinned'] !== $b['is_pinned']) {
                return $b['is_pinned'] - $a['is_pinned'];
            }
            $ta = $a['last_message']['created_at'] ?? $a['group_created_at'];
            $tb = $b['last_message']['created_at'] ?? $b['group_created_at'];
            return strcmp($tb, $ta);
        });

        return $threads;
    }

    /**
     * Get one thread's full detail: metadata + participants + paginated messages.
     * Also marks the thread as read for this user.
     */
    public function get_thread($group_id, $user_id, $limit = 50, $offset = 0)
    {
        $thread = $this->db->get_where('chat_groups', ['id' => $group_id])->row_array();
        if (!$thread) return null;

        $thread['participants'] = $this->get_thread_participants($group_id);

        $this->db->select('cm.*, u.fullname AS sender_name, u.avatar AS sender_avatar')
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

        $thread['messages'] = array_reverse($rows); // chronological

        // Mark as read for this user
        $this->mark_thread_read($group_id, $user_id);

        return $thread;
    }

    /**
     * Get participants of a thread.
     */
    public function get_thread_participants($group_id)
    {
        $rows = $this->db
            ->select('u.id AS user_id, u.fullname, u.avatar AS image, cgm.role, cgm.joined_at')
            ->from('chat_group_members cgm')
            ->join('users u', 'u.id = cgm.user_id', 'left')
            ->where('cgm.group_id', $group_id)
            ->get()->result_array();

        foreach ($rows as &$p) {
            if (!empty($p['image'])) {
                $p['image'] = site_url($p['image']);
            }
        }

        return $rows;
    }

    // =========================================================================
    // DIRECT MESSAGES
    // =========================================================================

    /**
     * Find an existing direct (DM) thread between exactly two users.
     * Returns the group_id or null.
     */
    public function find_direct_thread($user1_id, $user2_id)
    {
        $sql = "
            SELECT cg.id
            FROM chat_groups cg
            INNER JOIN chat_group_members m1 ON m1.group_id = cg.id AND m1.user_id = ?
            INNER JOIN chat_group_members m2 ON m2.group_id = cg.id AND m2.user_id = ?
            WHERE cg.type = 'direct'
            LIMIT 1
        ";
        $row = $this->db->query($sql, [(int)$user1_id, (int)$user2_id])->row();
        return $row ? (int)$row->id : null;
    }

    // =========================================================================
    // CREATE THREAD / GROUP
    // =========================================================================

    /**
     * Create a new thread (DM or group) and add initial members.
     *
     * @param string $name        Thread/group name (for DMs this can be null or auto-generated)
     * @param string $type        'direct' | 'group'
     * @param int    $created_by  User ID of creator
     * @param array  $member_ids  All user IDs to add (including creator)
     * @param array  $extra       Optional extra fields (description, is_private, etc.)
     * @return int  New group_id
     */
    public function create_thread($name, $type, $created_by, array $member_ids, array $extra = [])
    {
        $data = array_merge([
            'name'       => $name,
            'type'       => $type,
            'created_by' => $created_by,
            'is_private' => ($type === 'direct') ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ], $extra);

        $this->db->insert('chat_groups', $data);
        $group_id = $this->db->insert_id();

        foreach (array_unique($member_ids) as $uid) {
            $this->db->insert('chat_group_members', [
                'group_id'  => $group_id,
                'user_id'   => (int)$uid,
                'role'      => ((int)$uid === (int)$created_by) ? 'admin' : 'member',
                'joined_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $group_id;
    }

    // =========================================================================
    // MESSAGES
    // =========================================================================

    /**
     * Insert a new message row.
     * Expected keys: group_id, user_id, message, attachment, attachment_type
     */
    public function insert_message(array $data)
    {
        $this->db->insert('chat_messages', $data);
        return $this->db->insert_id();
    }

    /**
     * Fetch the full message row by ID.
     */
    public function get_message($message_id)
    {
        return $this->db->get_where('chat_messages', ['id' => $message_id])->row_array();
    }

    /**
     * Soft-delete a message: clears content, sets is_deleted = 1.
     * Only the sender (user_id) may delete their own message.
     */
    public function soft_delete_message($message_id, $user_id)
    {
        $msg = $this->db->get_where('chat_messages', [
            'id'      => (int)$message_id,
            'user_id' => (int)$user_id,
        ])->row();

        if (!$msg) return false;

        // Delete physical file if attachment exists
        if (!empty($msg->attachment)) {
            $path = FCPATH . str_replace(base_url(), '', $msg->attachment);
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $this->db->where('id', (int)$message_id)
            ->update('chat_messages', [
                'is_deleted'      => 1,
                'message'         => null,
                'attachment'      => null,
                'attachment_type' => null,
            ]);

        return true;
    }

    // =========================================================================
    // READ STATUS
    // =========================================================================

    /**
     * Mark all messages in a thread as read for the given user
     * by updating last_read_at on their membership row.
     */
    public function mark_thread_read($group_id, $user_id)
    {
        $this->db->where('group_id', (int)$group_id)
            ->where('user_id', (int)$user_id)
            ->update('chat_group_members', ['last_read_at' => date('Y-m-d H:i:s')]);
    }

    // =========================================================================
    // MEMBERSHIP
    // =========================================================================

    public function is_member($group_id, $user_id)
    {
        return (bool) $this->db->get_where('chat_group_members', [
            'group_id' => (int)$group_id,
            'user_id'  => (int)$user_id,
        ])->row();
    }

    public function add_member($group_id, $user_id, $role = 'member')
    {
        if ($this->is_member($group_id, $user_id)) return false;

        return $this->db->insert('chat_group_members', [
            'group_id'  => (int)$group_id,
            'user_id'   => (int)$user_id,
            'role'      => $role,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function remove_member($group_id, $user_id)
    {
        return $this->db->delete('chat_group_members', [
            'group_id' => (int)$group_id,
            'user_id'  => (int)$user_id,
        ]);
    }

    public function pin_thread($group_id, $user_id, $pin = true)
    {
        $this->db->where('group_id', (int)$group_id)
            ->where('user_id', (int)$user_id)
            ->update('chat_group_members', ['is_pinned' => $pin ? 1 : 0]);
    }

    // =========================================================================
    // V2 METHODS — New Messaging Schema
    // Tables: message_threads, thread_participants, messages, messages_attachments
    // Users linked via users.id (member_id / sender_member_id)
    // =========================================================================

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Build the stable direct_key for a DM between two users.
     * Always sorted so 12__48 == 48__12.
     */
    public function v2_build_direct_key($uid1, $uid2)
    {
        $ids = [(int)$uid1, (int)$uid2];
        sort($ids);
        return implode('__', $ids);
    }

    // -------------------------------------------------------------------------
    // THREADS
    // -------------------------------------------------------------------------

    /**
     * Find an existing DM thread by direct_key.
     * Returns thread_id or null.
     */
    public function v2_find_direct_thread($direct_key)
    {
        $row = $this->db->get_where('message_threads', [
            'direct_key' => $direct_key,
            'type'       => 'direct',
        ])->row();
        return $row ? (int)$row->id : null;
    }

    /**
     * Create a new thread (DM or group) and add initial participants.
     * For DMs, only call this when the first message is sent.
     *
     * @param array $data        Insert data for message_threads
     * @param array $member_ids  All user IDs to add (including creator)
     * @return int  New thread_id
     */
    public function v2_create_thread(array $data, array $member_ids)
    {
        $created_by = $data['created_by'];
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('message_threads', $data);
        $thread_id = $this->db->insert_id();

        foreach (array_unique($member_ids) as $uid) {
            $this->db->insert('thread_participants', [
                'thread_id' => $thread_id,
                'member_id' => (int)$uid,
                'role'      => ((int)$uid === (int)$created_by) ? 'admin' : 'member',
                'joined_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $thread_id;
    }

    // -------------------------------------------------------------------------
    // GRADUATION-YEAR GROUP MANAGEMENT
    // -------------------------------------------------------------------------

    /**
     * Find or create the one canonical group thread for a graduation year.
     * Title format: "Class of YYYY". created_by = 0 means system-created.
     * Returns thread_id.
     */
    public function v2_ensure_year_group($year)
    {
        $year  = (int)$year;
        $title = 'Class of ' . $year;

        $existing = $this->db
            ->where('type', 'group')
            ->where('title', $title)
            ->get('message_threads')
            ->row();

        if ($existing) {
            return (int)$existing->id;
        }

        $this->db->insert('message_threads', [
            'type'       => 'group',
            'title'      => $title,
            'created_by' => 0,
            'category'   => 'community',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    /**
     * Sync a user into the correct graduation-year group.
     *
     * - Reads graduation_year from users table.
     * - If no graduation_year, does nothing.
     * - Soft-leaves any OTHER "Class of YYYY" threads the user is active in.
     * - Adds or reactivates the user in the correct year thread.
     */
    public function v2_sync_user_year_group($user_id)
    {
        $user = $this->db
            ->select('graduation_year')
            ->get_where('users', ['id' => (int)$user_id])
            ->row();

        if (!$user || empty($user->graduation_year)) {
            return;
        }

        $year      = (int)$user->graduation_year;
        $thread_id = $this->v2_ensure_year_group($year);

        // Soft-leave any other Class-of-* threads this user is still active in
        $other_year_threads = $this->db
            ->select('tp.thread_id')
            ->from('thread_participants tp')
            ->join('message_threads mt', 'mt.id = tp.thread_id')
            ->where('tp.member_id', (int)$user_id)
            ->where('tp.left_at IS NULL')
            ->where('mt.type', 'group')
            ->like('mt.title', 'Class of ', 'after')
            ->where('mt.id !=', $thread_id)
            ->get()->result_array();

        foreach ($other_year_threads as $row) {
            $this->v2_leave_thread((int)$row['thread_id'], $user_id);
        }

        // Add to correct year thread (or reactivate if previously left)
        $this->v2_add_participant($thread_id, $user_id, 'member');
    }

    /**
     * Sync ALL users that have a graduation_year into their correct year group.
     * Safe to re-run — idempotent.
     */
    public function v2_sync_all_year_groups()
    {
        $users = $this->db
            ->select('id')
            ->where('graduation_year IS NOT NULL')
            ->where('graduation_year !=', 0)
            ->get('users')
            ->result_array();

        foreach ($users as $u) {
            $this->v2_sync_user_year_group((int)$u['id']);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Get all threads (inbox) for a user.
     * Uses denormalized last_message_* columns — no per-thread subqueries.
     * Lazily syncs the user into their graduation-year group on each inbox load.
     */
    public function v2_get_threads($user_id)
    {
        // Lazy sync — ensures the user is always in the right class group
        $this->v2_sync_user_year_group($user_id);

        $rows = $this->db
            ->select('
                mt.id AS thread_id,
                mt.type,
                mt.title,
                mt.category,
                mt.direct_key,
                mt.last_message_id,
                mt.last_message_at,
                mt.last_message_preview,
                mt.last_message_sender_name,
                mt.last_message_sender_member_id,
                mt.attachment_enabled,
                mt.audio_enabled,
                mt.created_at  AS thread_created_at,
                mt.updated_at,
                tp.role,
                tp.is_pinned,
                tp.joined_at,
                tp.last_read_message_id,
                tp.last_delivered_message_id
            ')
            ->from('thread_participants tp')
            ->join('message_threads mt', 'mt.id = tp.thread_id')
            ->where('tp.member_id', (int)$user_id)
            ->where('tp.left_at IS NULL')
            ->get()->result_array();

        foreach ($rows as &$thread) {
            $tid = $thread['thread_id'];
            $thread['unread_count'] = $this->v2_get_unread_count($tid, $user_id, $thread['last_read_message_id']);
            $thread['participants'] = $this->v2_get_participants($tid);

            // Compute last_message_status only when the viewer sent the last message
            $thread['last_message_status'] = null;
            $last_msg_id     = (int)($thread['last_message_id'] ?? 0);
            $last_sender_id  = (int)($thread['last_message_sender_member_id'] ?? 0);

            if ($last_msg_id && $last_sender_id === (int)$user_id) {
                $status = 'sent';
                foreach ($thread['participants'] as $p) {
                    if ((int)$p['member_id'] === (int)$user_id) continue;
                    $read_cursor      = (int)($p['last_read_message_id']      ?? 0);
                    $delivered_cursor = (int)($p['last_delivered_message_id'] ?? 0);
                    if ($read_cursor >= $last_msg_id) {
                        $status = 'seen';
                        break;
                    }
                    if ($delivered_cursor >= $last_msg_id && $status !== 'seen') {
                        $status = 'delivered';
                    }
                }
                $thread['last_message_status'] = $status;
            }
        }

        // Pinned first, then latest activity
        usort($rows, function ($a, $b) {
            if ($a['is_pinned'] !== $b['is_pinned']) return $b['is_pinned'] - $a['is_pinned'];
            $ta = $a['last_message_at'] ?? $a['thread_created_at'];
            $tb = $b['last_message_at'] ?? $b['thread_created_at'];
            return strcmp($tb, $ta);
        });

        return $rows;
    }

    /**
     * Get one thread: metadata + participants + paginated messages + attachments + reply previews.
     * Also marks delivered and read up to the latest message.
     */
    public function v2_get_thread($thread_id, $user_id, $limit = 50, $offset = 0)
    {
        $thread = $this->db->get_where('message_threads', ['id' => (int)$thread_id])->row_array();
        if (!$thread) return null;

        $thread['participants'] = $this->v2_get_participants($thread_id);

        $this->db
            ->select('m.*, u.fullname AS sender_name, u.avatar AS sender_avatar')
            ->from('messages m')
            ->join('users u', 'u.id = m.sender_member_id', 'left')
            ->where('m.thread_id', (int)$thread_id)
            ->order_by('m.created_at', 'DESC')
            ->limit($limit, $offset);

        $messages = $this->db->get()->result_array();

        foreach ($messages as &$msg) {
            if (!empty($msg['sender_avatar'])) {
                $msg['sender_avatar'] = site_url($msg['sender_avatar']);
            }
            $msg['attachments']   = $this->v2_get_message_attachments($msg['id']);
            $msg['reply_preview'] = !empty($msg['reply_to_message_id'])
                ? $this->v2_get_reply_preview($msg['reply_to_message_id'])
                : null;
        }

        $thread['messages'] = array_reverse($messages); // chronological order

        // Advance delivered + read cursors to latest message
        if (!empty($messages)) {
            $latest_id = $messages[0]['id']; // first = DESC = latest
            $this->v2_mark_delivered($thread_id, $user_id, $latest_id);
            $this->v2_mark_read($thread_id, $user_id, $latest_id);
        }

        return $thread;
    }

    /**
     * Get all active participants of a thread with profile data from users table.
     */
    public function v2_get_participants($thread_id)
    {
        $rows = $this->db
            ->select('
                u.id AS member_id,
                u.fullname,
                u.avatar,
                tp.role,
                tp.joined_at,
                tp.left_at,
                tp.is_pinned,
                tp.last_read_message_id,
                tp.last_delivered_message_id
            ')
            ->from('thread_participants tp')
            ->join('users u', 'u.id = tp.member_id', 'left')
            ->where('tp.thread_id', (int)$thread_id)
            ->get()->result_array();

        foreach ($rows as &$p) {
            if (!empty($p['avatar'])) {
                $p['avatar'] = site_url($p['avatar']);
            }
        }

        return $rows;
    }

    /**
     * Check if a user is an active participant (not left) of a thread.
     */
    public function v2_is_participant($thread_id, $user_id)
    {
        $row = $this->db->get_where('thread_participants', [
            'thread_id' => (int)$thread_id,
            'member_id' => (int)$user_id,
        ])->row();
        return $row && $row->left_at === null;
    }

    // -------------------------------------------------------------------------
    // MESSAGES
    // -------------------------------------------------------------------------

    /**
     * Insert a new message. Deduplicates by client_generated_id.
     * Also updates the thread's last_message_* summary fields.
     */
    public function v2_insert_message(array $data)
    {
        // Dedup — if frontend retries, return existing id
        if (!empty($data['client_generated_id'])) {
            $existing = $this->db->get_where('messages', [
                'thread_id'           => $data['thread_id'],
                'client_generated_id' => $data['client_generated_id'],
            ])->row();
            if ($existing) return (int)$existing->id;
        }

        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $this->db->insert('messages', $data);
        $message_id = $this->db->insert_id();

        // Update denormalized thread summary
        $sender = $this->db->get_where('users', ['id' => $data['sender_member_id']])->row();
        $sender_name = $sender ? $sender->fullname : 'User';
        $preview     = !empty($data['body']) ? substr($data['body'], 0, 200) : '[attachment]';

        $this->v2_update_thread_summary(
            $data['thread_id'],
            $message_id,
            $data['created_at'],
            $preview,
            $sender_name,
            $data['sender_member_id']
        );

        return $message_id;
    }

    /**
     * Fetch a single message row by id.
     */
    public function v2_get_message($message_id)
    {
        return $this->db->get_where('messages', ['id' => (int)$message_id])->row_array();
    }

    /**
     * Soft-delete a message: clears body, sets deleted_at.
     * Only the original sender can delete their own message.
     */
    public function v2_delete_message($message_id, $user_id)
    {
        $msg = $this->db->get_where('messages', [
            'id'               => (int)$message_id,
            'sender_member_id' => (int)$user_id,
        ])->row();

        if (!$msg) return false;

        // Remove physical attachment files
        $attachments = $this->v2_get_message_attachments($message_id);
        foreach ($attachments as $att) {
            if (!empty($att['storage_path']) && file_exists(FCPATH . $att['storage_path'])) {
                @unlink(FCPATH . $att['storage_path']);
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', (int)$message_id)
            ->update('messages', [
                'body'       => '',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        return true;
    }

    /**
     * Build reply preview data for a quoted message.
     */
    public function v2_get_reply_preview($message_id)
    {
        $msg = $this->db
            ->select('m.id, m.body, m.deleted_at, u.fullname AS sender_name')
            ->from('messages m')
            ->join('users u', 'u.id = m.sender_member_id', 'left')
            ->where('m.id', (int)$message_id)
            ->get()->row_array();

        if (!$msg) return null;
        if (!empty($msg['deleted_at'])) $msg['body'] = '[Message removed]';
        return $msg;
    }

    // -------------------------------------------------------------------------
    // ATTACHMENTS
    // -------------------------------------------------------------------------

    /**
     * Store attachment metadata. message_id is NULL until Send is pressed.
     * Returns attachment_id so the frontend can attach it on send.
     */
    public function v2_insert_attachment(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('messages_attachments', $data);
        return $this->db->insert_id();
    }

    /**
     * Link a staged attachment to a sent message.
     */
    public function v2_link_attachment($attachment_id, $message_id)
    {
        $this->db->where('id', (int)$attachment_id)
            ->update('messages_attachments', ['message_id' => (int)$message_id]);
    }

    /**
     * Get all attachments for a message.
     */
    public function v2_get_message_attachments($message_id)
    {
        return $this->db
            ->get_where('messages_attachments', ['message_id' => (int)$message_id])
            ->result_array();
    }

    // -------------------------------------------------------------------------
    // READ / DELIVERED CURSORS
    // -------------------------------------------------------------------------

    /**
     * Mark thread as read for user up to message_id.
     * Reading implies delivered too.
     */
    public function v2_mark_read($thread_id, $user_id, $message_id)
    {
        $this->db->where('thread_id', (int)$thread_id)
            ->where('member_id', (int)$user_id)
            ->where('(last_read_message_id IS NULL OR last_read_message_id < ' . (int)$message_id . ')', null, false)
            ->update('thread_participants', [
                'last_read_message_id'      => (int)$message_id,
                'last_delivered_message_id' => (int)$message_id,
            ]);
    }

    /**
     * Mark messages as delivered (client received, not necessarily read).
     */
    public function v2_mark_delivered($thread_id, $user_id, $message_id)
    {
        $this->db->where('thread_id', (int)$thread_id)
            ->where('member_id', (int)$user_id)
            ->where('(last_delivered_message_id IS NULL OR last_delivered_message_id < ' . (int)$message_id . ')', null, false)
            ->update('thread_participants', [
                'last_delivered_message_id' => (int)$message_id,
            ]);
    }

    /**
     * Count unread messages for a user in a thread.
     * Uses last_read_message_id cursor — no timestamps needed.
     */
    public function v2_get_unread_count($thread_id, $user_id, $last_read_message_id)
    {
        $this->db->from('messages')
            ->where('thread_id', (int)$thread_id)
            ->where('sender_member_id !=', (int)$user_id)
            ->where('deleted_at IS NULL');

        if (!empty($last_read_message_id)) {
            $this->db->where('id >', (int)$last_read_message_id);
        }

        return $this->db->count_all_results();
    }

    /**
     * Update the denormalized last_message_* fields on a thread row.
     * Called after every successful message insert.
     */
    public function v2_update_thread_summary($thread_id, $message_id, $last_message_at, $preview, $sender_name, $sender_member_id = null)
    {
        $this->db->where('id', (int)$thread_id)
            ->update('message_threads', [
                'last_message_id'                => (int)$message_id,
                'last_message_at'                => $last_message_at,
                'last_message_preview'           => substr($preview, 0, 300),
                'last_message_sender_name'       => $sender_name,
                'last_message_sender_member_id'  => $sender_member_id ? (int)$sender_member_id : null,
                'updated_at'                     => date('Y-m-d H:i:s'),
            ]);
    }

    // -------------------------------------------------------------------------
    // MEMBERSHIP
    // -------------------------------------------------------------------------

    /**
     * Add a participant. If they previously left, clears left_at (rejoin).
     * Returns false if already an active member.
     */
    public function v2_add_participant($thread_id, $user_id, $role = 'member')
    {
        $existing = $this->db->get_where('thread_participants', [
            'thread_id' => (int)$thread_id,
            'member_id' => (int)$user_id,
        ])->row();

        if ($existing) {
            if ($existing->left_at !== null) {
                // Rejoin
                $this->db->where('id', $existing->id)
                    ->update('thread_participants', [
                        'left_at'   => null,
                        'joined_at' => date('Y-m-d H:i:s'),
                    ]);
                return true;
            }
            return false; // already active
        }

        return $this->db->insert('thread_participants', [
            'thread_id' => (int)$thread_id,
            'member_id' => (int)$user_id,
            'role'      => $role,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Soft-leave a thread — sets left_at, row stays for history.
     */
    public function v2_leave_thread($thread_id, $user_id)
    {
        $this->db->where('thread_id', (int)$thread_id)
            ->where('member_id', (int)$user_id)
            ->update('thread_participants', ['left_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Pin or unpin a thread for a specific user.
     */
    public function v2_pin_thread($thread_id, $user_id, $pin)
    {
        $this->db->where('thread_id', (int)$thread_id)
            ->where('member_id', (int)$user_id)
            ->update('thread_participants', ['is_pinned' => $pin ? 1 : 0]);
    }

}
