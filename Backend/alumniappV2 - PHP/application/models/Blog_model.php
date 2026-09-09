<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Blog_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function validate_image_belongs_to_post($post_id, $image_url)
    {
        return $this->resolve_post_image_url($post_id, $image_url) !== null;
    }

    /**
     * Resolve a caller-supplied image URL to the URL stored for this post.
     *
     * Matching ignores scheme/host so URLs still resolve after a domain move,
     * and falls back to the file name so a rewritten path still matches.
     * Returns the stored image_url, or null when the image is not on the post.
     */
    public function resolve_post_image_url($post_id, $image_url)
    {
        $image_url = trim((string) $image_url);
        if ($image_url === '') {
            return null;
        }

        $images = $this->db->where('post_id', $post_id)
            ->get('blog_gallery')
            ->result_array();

        if (empty($images)) {
            return null;
        }

        $needle_name = basename(parse_url($image_url, PHP_URL_PATH) ?? $image_url);

        foreach ($images as $image) {
            if ($image['image_url'] === $image_url) {
                return $image['image_url'];
            }
        }

        foreach ($images as $image) {
            $stored_name = !empty($image['file_name'])
                ? $image['file_name']
                : basename(parse_url($image['image_url'], PHP_URL_PATH) ?? $image['image_url']);

            if ($needle_name !== '' && $stored_name === $needle_name) {
                return $image['image_url'];
            }
        }

        return null;
    }

    /**
     * Expand a stored image reference into an absolute URL on the current host.
     *
     * Accepts values stored relative ("uploads/blog/gallery/x.jpg") as well as
     * legacy absolute URLs baked to a previous domain, so a domain move does
     * not strand existing rows. Anything that is not an upload path (an
     * externally hosted image, say) is returned untouched.
     */
    public function public_image_url($stored)
    {
        if (empty($stored) || !is_string($stored)) {
            return $stored;
        }

        $path = parse_url($stored, PHP_URL_PATH);
        if ($path === false || $path === null || $path === '') {
            return $stored;
        }

        $path = ltrim(str_replace('/./', '/', $path), '/');
        $path = preg_replace('#^(\./)+#', '', $path);

        // Only rewrite our own upload paths; leave foreign URLs alone
        if (strpos($path, 'uploads/') !== 0) {
            return $stored;
        }

        return base_url($path);
    }

    // ═══════════════════════════════════════════════════════════
    // HOMEPAGE METHODS
    // ═══════════════════════════════════════════════════════════

    public function get_homepage_content($admin = false)
    {
        $query = $this->db->get('homepage')->row_array();

        if (!$query) {
            $query = ['greeting_title' => '', 'greeting_message' => ''];
        }

        $carousel_query = $this->db->order_by('sort_order', 'ASC');
        if (!$admin) {
            $carousel_query->where('is_hidden', '0');
        }
        $carousel_query->where('deleted_at', NULL);
        $carousel = $carousel_query->get('homepage_carousel')->result_array();

        // Which image the greeting is overlaid on (only one at a time)
        $greeting_image_id = null;
        foreach ($carousel as $image) {
            if (($image['show_greeting'] ?? '0') === '1') {
                $greeting_image_id = (int) $image['id'];
                break;
            }
        }

        return [
            'greeting_title' => $query['greeting_title'] ?? '',
            'greeting_message' => $query['greeting_message'] ?? '',
            'greeting_image_id' => $greeting_image_id,
            'carousel_images' => $carousel
        ];
    }

    public function update_homepage_text($title, $message)
    {
        $existing = $this->db->get('homepage')->row_array();

        if (!$existing) {
            $this->db->insert('homepage', [
                'greeting_title' => $title,
                'greeting_message' => $message
            ]);
            return $this->db->insert_id();
        } else {
            $this->db->where('id', 1)->update('homepage', [
                'greeting_title' => $title,
                'greeting_message' => $message
            ]);
            return 1;
        }
    }

    public function add_carousel_image($image_url, $file_name, $alt_text = null, $sort_order = null)
    {
        if ($sort_order === null) {
            $max_order = $this->db->select('MAX(sort_order) as max_order')
                ->get('homepage_carousel')->row_array();
            $sort_order = ($max_order['max_order'] ?? 0) + 1;
        }

        $this->db->insert('homepage_carousel', [
            'image_url' => $image_url,
            'file_name' => $file_name,
            'alt_text' => $alt_text,
            'sort_order' => $sort_order,
            'is_hidden' => '0',
            'show_greeting' => '0'
        ]);

        return $this->db->insert_id();
    }

    public function update_carousel_image($id, $data)
    {
        $this->db->where('id', $id)->update('homepage_carousel', $data);
        return $this->db->get_where('homepage_carousel', ['id' => $id])->row_array();
    }

    /**
     * Put the greeting message on one image, clearing it everywhere else.
     * Only one carousel image may carry the greeting at a time.
     */
    public function set_greeting_image($id)
    {
        $this->db->trans_start();

        $this->db->where('id !=', $id)
            ->where('show_greeting', '1')
            ->update('homepage_carousel', ['show_greeting' => '0']);

        $this->db->where('id', $id)->update('homepage_carousel', ['show_greeting' => '1']);

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return FALSE;
        }

        // Read back, so a rolled back or no-op write is reported as a failure
        // rather than surfacing as a 200 with nothing changed
        $row = $this->db->select('show_greeting')
            ->get_where('homepage_carousel', ['id' => $id])
            ->row_array();

        return $row && $row['show_greeting'] === '1';
    }

    /**
     * Take the greeting off one image.
     */
    public function clear_greeting_image($id)
    {
        return $this->db->where('id', $id)
            ->update('homepage_carousel', ['show_greeting' => '0']);
    }

    /**
     * Called when an image is hidden or deleted. If that image was carrying
     * the greeting, move it to the next visible image by sort_order so the
     * greeting does not silently disappear from the homepage.
     *
     * Returns the id that now carries the greeting, or null if nothing does.
     */
    public function reassign_greeting_image($id)
    {
        $image = $this->db->get_where('homepage_carousel', ['id' => $id])->row_array();

        // Nothing to move - this image was not carrying the greeting
        if (!$image || ($image['show_greeting'] ?? '0') !== '1') {
            return null;
        }

        $this->db->where('id', $id)->update('homepage_carousel', ['show_greeting' => '0']);

        $next = $this->db
            ->where('id !=', $id)
            ->where('is_hidden', '0')
            ->where('deleted_at', NULL)
            ->order_by('sort_order', 'ASC')
            ->limit(1)
            ->get('homepage_carousel')
            ->row_array();

        // No visible image left to hold it
        if (!$next) {
            return null;
        }

        $this->db->where('id', $next['id'])->update('homepage_carousel', ['show_greeting' => '1']);

        return (int) $next['id'];
    }

    public function get_carousel_image($id)
    {
        return $this->db->get_where('homepage_carousel', ['id' => $id])->row_array();
    }

    public function reorder_carousel($images)
    {
        foreach ($images as $image) {
            $this->db->where('id', $image['id'])->update('homepage_carousel', [
                'sort_order' => $image['sort_order']
            ]);
        }
        return true;
    }

    public function delete_carousel_image($id)
    {
        // Move the greeting off this image first, while it is still readable
        $this->reassign_greeting_image($id);

        $this->db->where('id', $id)->update('homepage_carousel', [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        // Normalize ordering
        $this->normalize_carousel_order();
    }

    public function normalize_carousel_order()
    {
        $images = $this->db
            ->where('deleted_at', NULL)
            ->order_by('sort_order', 'ASC')
            ->get('homepage_carousel')->result_array();

        foreach ($images as $index => $image) {
            $this->db->where('id', $image['id'])->update('homepage_carousel', [
                'sort_order' => $index
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // FAQ METHODS
    // ═══════════════════════════════════════════════════════════

    public function get_faqs($admin = false)
    {
        $query = $this->db->where('deleted_at', NULL);
        if (!$admin) {
            $query->where('is_published', '1');
        }
        return $query->order_by('sort_order', 'ASC')->get('faqs')->result_array();
    }

    public function create_faq($question, $answer, $sort_order = null, $is_published = '1')
    {
        if ($sort_order === null) {
            $max_order = $this->db->select('MAX(sort_order) as max_order')
                ->get('faqs')->row_array();
            $sort_order = ($max_order['max_order'] ?? 0) + 1;
        }

        $this->db->insert('faqs', [
            'question' => $question,
            'answer' => $answer,
            'sort_order' => $sort_order,
            'is_published' => $is_published
        ]);

        return $this->db->insert_id();
    }

    public function get_faq($id)
    {
        return $this->db->where('deleted_at', NULL)->get_where('faqs', ['id' => $id])->row_array();
    }

    public function update_faq($id, $data)
    {
        $this->db->where('id', $id)->update('faqs', $data);
        return $this->get_faq($id);
    }

    public function reorder_faqs($faqs)
    {
        foreach ($faqs as $faq) {
            $this->db->where('id', $faq['id'])->update('faqs', [
                'sort_order' => $faq['sort_order']
            ]);
        }
        return true;
    }

    public function delete_faq($id)
    {
        $this->db->where('id', $id)->update('faqs', [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
        $this->normalize_faq_order();
    }

    public function normalize_faq_order()
    {
        $faqs = $this->db
            ->where('deleted_at', NULL)
            ->order_by('sort_order', 'ASC')
            ->get('faqs')->result_array();

        foreach ($faqs as $index => $faq) {
            $this->db->where('id', $faq['id'])->update('faqs', [
                'sort_order' => $index
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // BLOG CATEGORY METHODS
    // ═══════════════════════════════════════════════════════════

    public function get_categories($admin = false)
    {
        $query = $this->db->where('deleted_at', NULL)->order_by('sort_order', 'ASC');
        if (!$admin) {
            $query->where('is_active', '1');
        }
        return $query->get('blog_categories')->result_array();
    }

    public function get_category($id)
    {
        return $this->db->where('deleted_at', NULL)
            ->get_where('blog_categories', ['id' => $id])
            ->row_array();
    }

    public function create_blog_category($name, $slug = null, $is_active = '1', $sort_order = null)
    {
        if (!$slug) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name)));
        }

        if ($sort_order === null) {
            $max_order = $this->db->select('MAX(sort_order) as max_order')
                ->get('blog_categories')->row_array();
            $sort_order = ($max_order['max_order'] ?? 0) + 1;
        }

        // slug is UNIQUE and soft deleted rows keep theirs, so recreating a
        // deleted category would hit a duplicate key. Revive that row instead.
        $deleted = $this->db->where('slug', $slug)
            ->where('deleted_at IS NOT NULL')
            ->get('blog_categories')
            ->row_array();

        if ($deleted) {
            $this->db->where('id', $deleted['id'])->update('blog_categories', [
                'name' => $name,
                'is_active' => $is_active,
                'sort_order' => $sort_order,
                'deleted_at' => NULL
            ]);

            return (int) $deleted['id'];
        }

        $this->db->insert('blog_categories', [
            'name' => $name,
            'slug' => $slug,
            'is_active' => $is_active,
            'sort_order' => $sort_order
        ]);

        return $this->db->insert_id();
    }

    public function update_blog_category($id, $data)
    {
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $data['name'])));
        }

        $this->db->where('id', $id)->update('blog_categories', $data);
        return $this->get_category($id);
    }

    /**
     * Soft delete. blog_posts.category_id references this table with an
     * implicit ON DELETE RESTRICT, so a physical delete throws a foreign key
     * error whenever any post - including a soft deleted one - still points
     * here. Flagging the row keeps the reference valid.
     */
    public function delete_blog_category($id)
    {
        $this->db->where('id', $id)->update('blog_categories', [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        $this->normalize_category_order();
    }

    public function reorder_categories($categories)
    {
        foreach ($categories as $category) {
            $this->db->where('id', $category['id'])->update('blog_categories', [
                'sort_order' => $category['sort_order']
            ]);
        }
        return true;
    }

    public function normalize_category_order()
    {
        $categories = $this->db
            ->where('deleted_at', NULL)
            ->order_by('sort_order', 'ASC')
            ->get('blog_categories')->result_array();

        foreach ($categories as $index => $category) {
            $this->db->where('id', $category['id'])->update('blog_categories', [
                'sort_order' => $index
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // BLOG POST METHODS
    // ═══════════════════════════════════════════════════════════

    public function get_posts($filters = [], $admin = false)
    {
        $where = "bp.deleted_at IS NULL";

        if (!$admin) {
            $where .= " AND bp.status = 'published'";
        } elseif (isset($filters['status']) && $filters['status'] !== 'all') {
            $where .= " AND bp.status = '" . $this->db->escape_str($filters['status']) . "'";
        }

        if (isset($filters['category'])) {
            $where .= " AND bp.category_id = " . intval($filters['category']);
        }

        if (isset($filters['search'])) {
            $search = $this->db->escape_str($filters['search']);
            $where .= " AND (bp.title LIKE '%" . $search . "%' OR bp.excerpt LIKE '%" . $search . "%')";
        }

        $select = "bp.id, bp.slug, bp.title, bp.excerpt, bp.category_id, bc.name as category_name, bp.status, bp.cover_image_url, bp.read_time_minutes, bp.published_at, bp.created_at, bp.updated_at";

        // Pagination
        $page = intval($filters['page'] ?? 1);
        $limit = intval($filters['limit'] ?? 10);
        $offset = ($page - 1) * $limit;

        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM blog_posts bp WHERE " . $where;
        $count_result = $this->db->query($count_sql)->row_array();
        $total = $count_result['total'];

        // Get posts with pagination
        $sql = "SELECT " . $select . " FROM blog_posts bp LEFT JOIN blog_categories bc ON bp.category_id = bc.id WHERE " . $where . " ORDER BY bp.published_at DESC, bp.created_at DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);
        $posts = $this->db->query($sql)->result_array();

        foreach ($posts as &$post) {
            $post['cover_image_url'] = $this->public_image_url($post['cover_image_url']);
        }
        unset($post);

        return [
            'posts' => $posts,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }

    public function get_post_detail($id_or_slug, $admin = false)
    {
        $select = "bp.id, bp.slug, bp.title, bp.excerpt, bp.category_id, bc.name as category_name, bp.status, bp.cover_image_url, bp.read_time_minutes, bp.published_at, bp.created_at, bp.updated_at";

        $where = "bp.deleted_at IS NULL";

        if (is_numeric($id_or_slug)) {
            $where .= " AND bp.id = " . intval($id_or_slug);
        } else {
            $where .= " AND bp.slug = '" . $this->db->escape_str($id_or_slug) . "'";
        }

        if (!$admin) {
            $where .= " AND bp.status = 'published'";
        }

        $sql = "SELECT " . $select . " FROM blog_posts bp LEFT JOIN blog_categories bc ON bp.category_id = bc.id WHERE " . $where;
        $post = $this->db->query($sql)->row_array();

        if (!$post) {
            return null;
        }

        // Get sections
        $post['sections'] = $this->db
            ->where('post_id', $post['id'])
            ->order_by('sort_order', 'ASC')
            ->get('blog_sections')->result_array();

        // Get gallery
        $post['gallery_images'] = $this->db
            ->where('post_id', $post['id'])
            ->order_by('sort_order', 'ASC')
            ->get('blog_gallery')->result_array();

        $post['cover_image_url'] = $this->public_image_url($post['cover_image_url']);
        foreach ($post['gallery_images'] as &$image) {
            $image['image_url'] = $this->public_image_url($image['image_url']);
        }
        unset($image);

        return $post;
    }

    public function create_post($title, $category_id, $excerpt, $status, $sections = [], $gallery = [], $main_image_index = null)
    {
        $slug = $this->generate_slug($title);
        $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;

        $post_data = [
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'category_id' => $category_id,
            'status' => $status,
            'published_at' => $published_at
        ];

        if (!empty($gallery)) {
            $index = ($main_image_index !== null) ? intval($main_image_index) : 0;
            $post_data['cover_image_url'] = $gallery[$index]['image_url'];
        }

        $this->db->insert('blog_posts', $post_data);
        $post_id = $this->db->insert_id();

        // Add sections
        $this->add_post_sections($post_id, $sections);

        // Add gallery
        $this->add_gallery_images($post_id, $gallery);

        // Calculate read time
        $read_time = $this->calculate_read_time($sections);
        $this->db->where('id', $post_id)->update('blog_posts', [
            'read_time_minutes' => $read_time
        ]);

        return $post_id;
    }

    public function update_post($id, $title = null, $category_id = null, $excerpt = null, $status = null, $sections = [], $gallery = [], $cover_image_url = null)
    {
        $update_data = [];

        if ($title !== null) {
            $update_data['slug'] = $this->generate_slug($title);
            $update_data['title'] = $title;
        }

        if ($category_id !== null) {
            $update_data['category_id'] = $category_id;
        }

        if ($excerpt !== null) {
            $update_data['excerpt'] = $excerpt;
        }

        if ($status !== null) {
            $update_data['status'] = $status;
            if ($status === 'published') {
                $current = $this->db->get_where('blog_posts', ['id' => $id])->row_array();
                if (!$current['published_at']) {
                    $update_data['published_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        if (!empty($update_data)) {
            $this->db->where('id', $id)->update('blog_posts', $update_data);
        }

        // Update sections if provided
        if (!empty($sections)) {
            $this->db->where('post_id', $id)->delete('blog_sections');
            $this->add_post_sections($id, $sections);
        }

        // Update gallery if provided
        if (!empty($gallery)) {
            $this->db->where('post_id', $id)->delete('blog_gallery');
            $this->add_gallery_images($id, $gallery);

            // Use provided cover_image_url or default to first new image
            if ($cover_image_url !== null) {
                $this->db->where('id', $id)->update('blog_posts', [
                    'cover_image_url' => $cover_image_url
                ]);
            } else {
                $this->db->where('id', $id)->update('blog_posts', [
                    'cover_image_url' => $gallery[0]['image_url']
                ]);
            }
        } elseif ($cover_image_url !== null) {
            // Update cover image without changing gallery
            $this->db->where('id', $id)->update('blog_posts', [
                'cover_image_url' => $cover_image_url
            ]);
        }

        // Recalculate read time
        $sections = $sections ?: $this->db->get_where('blog_sections', ['post_id' => $id])->result_array();
        $read_time = $this->calculate_read_time($sections);
        $this->db->where('id', $id)->update('blog_posts', [
            'read_time_minutes' => $read_time
        ]);

        return $this->get_post_detail($id, true);
    }

    public function add_post_sections($post_id, $sections)
    {
        foreach ($sections as $section) {
            $this->db->insert('blog_sections', [
                'post_id' => $post_id,
                'heading' => $section['heading'] ?? '',
                'body' => $section['body'] ?? '',
                'sort_order' => $section['sort_order'] ?? 0
            ]);
        }
    }

    public function add_gallery_images($post_id, $images)
    {
        foreach ($images as $image) {
            $this->db->insert('blog_gallery', [
                'post_id' => $post_id,
                'image_url' => $image['image_url'],
                'file_name' => $image['file_name'] ?? null,
                'alt_text' => $image['alt_text'] ?? null,
                'sort_order' => $image['sort_order'] ?? 0
            ]);
        }
    }

    public function delete_post($id)
    {
        $this->db->where('id', $id)->update('blog_posts', [
            'deleted_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function generate_slug($title)
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $counter = 1;
        $original_slug = $slug;
        while ($this->db->where('slug', $slug)->count_all_results('blog_posts') > 0) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function calculate_read_time($sections)
    {
        $total_words = 0;
        foreach ($sections as $section) {
            $body = $section['body'] ?? '';
            $words = str_word_count(strip_tags($body));
            $total_words += $words;
        }

        $read_time = max(1, ceil($total_words / 200));
        return $read_time;
    }
}
