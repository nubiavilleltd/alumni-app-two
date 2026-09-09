<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Blog_api extends MY_Controller
{
    private $upload_errors = [];

    public function __construct()
    {
        parent::__construct();
        $this->load->model('blog_model');        
    }

    private function checkAuth($admin = false)
    {
        if (!$this->checkAPI_token_from_header()) {
            http_response_code(401);
            echo json_encode(['status' => 401, 'message' => 'Invalid API token']);
            return false;
        }

        if ($admin) {
            $jwtData = $this->checkJWT();
            if (!$jwtData) {
                http_response_code(403);
                echo json_encode(['status' => 403, 'message' => 'Admin authentication required']);
                return false;
            }

            $user_role = strtolower($jwtData->user_role ?? '');
            if (!in_array($user_role, ['admin', 'manager', 'superadmin'])) {
                http_response_code(403);
                echo json_encode(['status' => 403, 'message' => 'Admin access required']);
                return false;
            }
        }

        return true;
    }

    

    /**
     * Read the request body whatever the content type.
     * Needed because endpoints that accept a file upload arrive as
     * multipart/form-data, where php://input is empty.
     */
    private function requestInput()
    {
        $input = [];

        $contentType = (string) $this->input->server('CONTENT_TYPE');

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        // form-data / x-www-form-urlencoded, plus query string as a fallback
        if (!empty($_POST)) {
            $input = array_merge($input, $_POST);
        }
        if (!empty($_GET)) {
            $input = array_merge($_GET, $input);
        }

        // Raw JSON sent without the JSON content type header
        if (empty($input)) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        return $input;
    }

    /**
     * Normalise a boolean-ish value. form-data always arrives as a string, so
     * "true" / "1" / "yes" / "on" / "checked" all have to count as true.
     *
     * Anything not in the false list counts as true, rather than whitelisting
     * true values - an unrecognised value silently meaning "off" is impossible
     * to tell apart from a working request that saved nothing.
     */
    private function toFlag($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value != 0;
        }

        if ($value === null || is_array($value)) {
            return false;
        }

        return !in_array(
            strtolower(trim((string) $value)),
            ['0', 'false', 'no', 'off', 'null', 'undefined', ''],
            true
        );
    }

    private function uploadImage($field, $upload_path)
    {
        if (empty($_FILES[$field]['name'])) {
            return null;
        }

        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, true);
        }

        $config = [
            'upload_path' => $upload_path,
            'allowed_types' => 'jpg|jpeg|png|webp',
            'max_size' => 2048,
            'file_name' => time() . '_' . rand(1000, 9999)
        ];

        $this->load->library('upload', $config);

        if ($this->upload->do_upload($field)) {
            $file = $this->upload->data();
            return [
                'file_name' => $file['file_name'],
                'path' => $upload_path . $file['file_name'],
                // Host-independent path, safe to persist across domain moves
                'relative_path' => ltrim(str_replace('./', '', $upload_path), '/') . $file['file_name'],
                'url' => site_url($upload_path . $file['file_name'])
            ];
        }

        $this->upload_errors[] = $_FILES[$field]['name'] . ': ' . strip_tags($this->upload->display_errors('', ''));

        return null;
    }

    // ═════════════════════════════════════════════════════════════
    // HOMEPAGE ENDPOINTS
    // ═════════════════════════════════════════════════════════════

    public function homepage()
    {
        header('Content-Type: application/json');

        $admin = $this->checkAPI_token_from_header();
        $homepage = $this->blog_model->get_homepage_content($admin);

        echo json_encode([
            'status' => 200,
            'homepage' => $homepage
        ]);
    }

    public function update_homepage_text()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);

        $title = trim($object['greeting_title'] ?? '');
        $message = trim($object['greeting_message'] ?? '');

        if (empty($title) || empty($message)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'greeting_title and greeting_message are required']);
            return;
        }

        $this->blog_model->update_homepage_text($title, $message);

        echo json_encode([
            'status' => 200,
            'message' => 'Homepage text updated successfully',
            'data' => [
                'greeting_title' => $title,
                'greeting_message' => $message
            ]
        ]);
    }

    public function create_carousel_image()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $alt_text = $this->input->post('alt_text');
        $sort_order = $this->input->post('sort_order');

        $upload = $this->uploadImage('image', './uploads/homepage/carousel/');
        if (!$upload) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'Image upload failed']);
            return;
        }

        $id = $this->blog_model->add_carousel_image($upload['url'], $upload['file_name'], $alt_text, $sort_order);

        // Optional: make the new image the one carrying the greeting
        if ($this->toFlag($this->input->post('show_greeting'))) {
            $this->blog_model->set_greeting_image($id);
        }

        $homepage = $this->blog_model->get_homepage_content(true);

        echo json_encode([
            'status' => 200,
            'message' => 'Carousel image created successfully',
            'image' => $this->blog_model->get_carousel_image($id),
            'greeting_image_id' => $homepage['greeting_image_id']
        ]);
    }

    public function update_carousel_image()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        // Accepts JSON and multipart/form-data (form-data is required when
        // replacing the image, and php://input is empty in that case)
        $object = $this->requestInput();
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $existing = $this->blog_model->get_carousel_image($id);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Carousel image not found']);
            return;
        }

        $update_data = [];

        if (isset($object['alt_text'])) {
            $update_data['alt_text'] = $object['alt_text'];
        }

        $hiding = false;
        if (isset($object['is_hidden'])) {
            // Column is ENUM('0','1') - normalise "true"/"false"/1/0 from form-data
            $hiding = $this->toFlag($object['is_hidden']);
            $update_data['is_hidden'] = $hiding ? '1' : '0';
        }

        // "Show the greeting message on this image". Handled separately from
        // $update_data because turning it on has to clear every other image.
        $show_greeting = isset($object['show_greeting'])
            ? $this->toFlag($object['show_greeting'])
            : null;

        // Handle image replacement
        if (!empty($_FILES['image']['name'])) {
            $upload = $this->uploadImage('image', './uploads/homepage/carousel/');
            if (!$upload) {
                http_response_code(400);
                echo json_encode([
                    'status' => 400,
                    'message' => 'Image upload failed',
                    'error' => strip_tags($this->upload->display_errors('', ''))
                ]);
                return;
            }
            $update_data['image_url'] = $upload['url'];
            $update_data['file_name'] = $upload['file_name'];
        }

        if (empty($update_data) && $show_greeting === null) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'No fields to update']);
            return;
        }

        // The show_greeting column ships in a separate migration, so fail with a
        // useful message instead of a rolled back transaction and a fake 200
        if ($show_greeting !== null && !$this->db->field_exists('show_greeting', 'homepage_carousel')) {
            http_response_code(500);
            echo json_encode([
                'status' => 500,
                'message' => 'The show_greeting column is missing. Run migrations/2026_07_30_carousel_show_greeting.sql'
            ]);
            return;
        }

        if (!empty($update_data)) {
            $this->blog_model->update_carousel_image($id, $update_data);
        }

        if ($show_greeting === true) {
            if (!$this->blog_model->set_greeting_image($id)) {
                http_response_code(500);
                echo json_encode([
                    'status' => 500,
                    'message' => 'Could not set the greeting image'
                ]);
                return;
            }
        } elseif ($show_greeting === false) {
            $this->blog_model->clear_greeting_image($id);
        }

        // Hiding the image that carries the greeting moves it to the next
        // visible one, so the greeting never vanishes from the homepage
        if ($hiding) {
            $this->blog_model->reassign_greeting_image($id);
        }

        $homepage = $this->blog_model->get_homepage_content(true);
        $after = $this->blog_model->get_carousel_image($id);

        $response = [
            'status' => 200,
            'message' => 'Carousel image updated successfully',
            'image' => $after,
            'greeting_image_id' => $homepage['greeting_image_id'],
            'carousel_images' => $homepage['carousel_images']
        ];

        // Send ?debug=1 to see exactly what the endpoint received and did
        if ($this->toFlag($object['debug'] ?? $this->input->get('debug'))) {
            $response['debug'] = [
                'content_type' => $this->input->server('CONTENT_TYPE'),
                'request_method' => $this->input->server('REQUEST_METHOD'),
                'parsed_input_keys' => array_keys($object),
                'post_keys' => array_keys($_POST),
                'raw_body_length' => strlen(file_get_contents('php://input')),
                'show_greeting_received' => array_key_exists('show_greeting', $object),
                'show_greeting_raw' => $object['show_greeting'] ?? null,
                'show_greeting_parsed' => $show_greeting,
                'show_greeting_before' => $existing['show_greeting'] ?? '(column missing)',
                'show_greeting_after' => $after['show_greeting'] ?? '(column missing)',
                'last_query' => $this->db->last_query()
            ];
        }

        echo json_encode($response);
    }

    public function reorder_carousel()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $images = $object['images'] ?? [];

        if (empty($images)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'images array is required']);
            return;
        }

        $this->blog_model->reorder_carousel($images);
        $homepage = $this->blog_model->get_homepage_content(true);

        echo json_encode([
            'status' => 200,
            'message' => 'Carousel reordered successfully',
            'carousel_images' => $homepage['carousel_images']
        ]);
    }

    public function delete_carousel_image()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $this->blog_model->delete_carousel_image($id);
        $homepage = $this->blog_model->get_homepage_content(true);

        echo json_encode([
            'status' => 200,
            'message' => 'Carousel image deleted successfully',
            'greeting_image_id' => $homepage['greeting_image_id'],
            'carousel_images' => $homepage['carousel_images']
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // FAQ ENDPOINTS
    // ═════════════════════════════════════════════════════════════

    public function faqs()
    {
        header('Content-Type: application/json');

        $admin = $this->checkAPI_token_from_header();
        $faqs = $this->blog_model->get_faqs($admin);

        echo json_encode([
            'status' => 200,
            'faqs' => $faqs
        ]);
    }

    public function create_faq()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);

        $question = trim($object['question'] ?? '');
        $answer = trim($object['answer'] ?? '');
        $sort_order = $object['sort_order'] ?? null;
        $is_published = $object['is_published'] ?? '1';

        if (empty($question) || empty($answer)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'question and answer are required']);
            return;
        }

        $id = $this->blog_model->create_faq($question, $answer, $sort_order, $is_published);

        echo json_encode([
            'status' => 200,
            'message' => 'FAQ created successfully',
            'faq' => $this->blog_model->get_faq($id)
        ]);
    }

    public function update_faq()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $update_data = [];

        if (isset($object['question'])) {
            $update_data['question'] = trim($object['question']);
        }
        if (isset($object['answer'])) {
            $update_data['answer'] = trim($object['answer']);
        }
        if (isset($object['is_published'])) {
            $update_data['is_published'] = $object['is_published'];
        }

        if (empty($update_data)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'No fields to update']);
            return;
        }

        $faq = $this->blog_model->update_faq($id, $update_data);

        echo json_encode([
            'status' => 200,
            'message' => 'FAQ updated successfully',
            'faq' => $faq
        ]);
    }

    public function reorder_faqs()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $faqs = $object['faqs'] ?? [];

        if (empty($faqs)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'faqs array is required']);
            return;
        }

        $this->blog_model->reorder_faqs($faqs);
        $result = $this->blog_model->get_faqs(true);

        echo json_encode([
            'status' => 200,
            'message' => 'FAQs reordered successfully',
            'faqs' => $result
        ]);
    }

    public function delete_faq()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $this->blog_model->delete_faq($id);

        echo json_encode([
            'status' => 200,
            'message' => 'FAQ deleted successfully'
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // BLOG CATEGORY ENDPOINTS
    // ═════════════════════════════════════════════════════════════

    public function blog_categories()
    {
        header('Content-Type: application/json');

        $admin = $this->checkAPI_token_from_header();
        $categories = $this->blog_model->get_categories($admin);

        echo json_encode([
            'status' => 200,
            'categories' => $categories
        ]);
    }

    public function create_blog_category()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);

        $name = trim($object['name'] ?? '');
        $slug = trim($object['slug'] ?? '');
        $is_active = $object['is_active'] ?? '1';
        $sort_order = $object['sort_order'] ?? null;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'name is required']);
            return;
        }

        $id = $this->blog_model->create_blog_category($name, $slug ?: null, $is_active, $sort_order);

        echo json_encode([
            'status' => 200,
            'message' => 'Category created successfully',
            'category' => $this->blog_model->get_category($id)
        ]);
    }

    public function update_blog_category()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        if (!$this->blog_model->get_category($id)) {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Category not found']);
            return;
        }

        $update_data = [];

        if (isset($object['name'])) {
            $update_data['name'] = trim($object['name']);
        }

        if (isset($object['slug'])) {
            $update_data['slug'] = trim($object['slug']);
        }

        if (isset($object['is_active'])) {
            $update_data['is_active'] = $object['is_active'];
        }

        if (empty($update_data)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'No fields to update']);
            return;
        }

        $category = $this->blog_model->update_blog_category($id, $update_data);

        echo json_encode([
            'status' => 200,
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    public function delete_blog_category()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = $this->requestInput();
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        if (!$this->blog_model->get_category($id)) {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Category not found']);
            return;
        }

        $this->blog_model->delete_blog_category($id);

        echo json_encode([
            'status' => 200,
            'message' => 'Category deleted successfully',
            'categories' => $this->blog_model->get_categories(true)
        ]);
    }

    public function reorder_categories()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $categories = $object['categories'] ?? [];

        if (empty($categories)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'categories array is required']);
            return;
        }

        $this->blog_model->reorder_categories($categories);
        $result = $this->blog_model->get_categories(true);

        echo json_encode([
            'status' => 200,
            'message' => 'Categories reordered successfully',
            'categories' => $result
        ]);
    }

    // ═════════════════════════════════════════════════════════════
    // BLOG POST ENDPOINTS
    // ═════════════════════════════════════════════════════════════

    public function blog_posts()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth()) {
            return;
        }

        $admin = $this->checkJWT() ? true : false;

        $filters = [
            'status' => $_GET['status'] ?? ($admin ? 'all' : 'published'),
            'category' => $_GET['category'] ?? null,
            'search' => $_GET['search'] ?? null,
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 10
        ];

        $result = $this->blog_model->get_posts($filters, $admin);

        echo json_encode([
            'status' => 200,
            'posts' => $result['posts'],
            'pagination' => $result['pagination']
        ]);
    }

    public function blog_post_detail($id_or_slug = null)
    {
        header('Content-Type: application/json');

        if (!$id_or_slug) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id or slug is required']);
            return;
        }

        $admin = $this->checkAPI_token_from_header();
        $post = $this->blog_model->get_post_detail($id_or_slug, $admin);

        if (!$post) {
            http_response_code(404);
            echo json_encode(['status' => 404, 'message' => 'Post not found']);
            return;
        }

        echo json_encode([
            'status' => 200,
            'post' => $post
        ]);
    }

    public function create_blog_post()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $title = trim($this->input->post('title') ?? '');
        $category_id = intval($this->input->post('category_id') ?? 0);
        $excerpt = trim($this->input->post('excerpt') ?? '');
        $status = trim($this->input->post('status') ?? 'draft');

        if (empty($title) || !$category_id || empty($excerpt)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'title, category_id, and excerpt are required']);
            return;
        }

        // Get sections from JSON
        $sections_json = $this->input->post('sections');
        $sections = json_decode($sections_json, true) ?? [];

        if (empty($sections)) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'At least one section is required']);
            return;
        }

        // Handle image uploads
        $gallery = [];
        if (!empty($_FILES['images']['name'])) {
            $file_names = $_FILES['images']['name'];
            if (!is_array($file_names)) {
                $file_names = [$file_names];
                $_FILES['images']['type'] = [$_FILES['images']['type']];
                $_FILES['images']['tmp_name'] = [$_FILES['images']['tmp_name']];
                $_FILES['images']['error'] = [$_FILES['images']['error']];
                $_FILES['images']['size'] = [$_FILES['images']['size']];
            }

            $file_count = count($file_names);
            for ($i = 0; $i < $file_count; $i++) {
                $_FILES['image'] = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i]
                ];

                $upload = $this->uploadImage('image', './uploads/blog/gallery/');
                if ($upload) {
                    $gallery[] = [
                        // Stored relative; Blog_model expands it to the current host on read
                        'image_url' => $upload['relative_path'],
                        'file_name' => $upload['file_name'],
                        'alt_text' => '',
                        'sort_order' => $i
                    ];
                }
            }
        }

        // Get main_image_index for selecting cover image
        $main_image_index = $this->input->post('main_image_index');

        // Validate main_image_index if provided
        if ($main_image_index !== null && $main_image_index !== '' && !empty($gallery)) {
            $index = intval($main_image_index);
            if ($index < 0 || $index >= count($gallery)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 400,
                    'message' => 'main_image_index must be a valid zero-based index into uploaded images. Provided: ' . $index . ', Available: ' . count($gallery),
                    'upload_errors' => $this->upload_errors
                ]);
                return;
            }
        } else {
            $main_image_index = null;
        }

        $post_id = $this->blog_model->create_post($title, $category_id, $excerpt, $status, $sections, $gallery, $main_image_index);

        echo json_encode([
            'status' => 200,
            'message' => 'Blog post created successfully',
            'post' => $this->blog_model->get_post_detail($post_id, true)
        ]);
    }

    public function update_blog_post()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $id = intval($this->input->post('id') ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $title = $this->input->post('title');
        $category_id = $this->input->post('category_id');
        $excerpt = $this->input->post('excerpt');
        $status = $this->input->post('status');

        $sections_json = $this->input->post('sections');
        $sections = $sections_json ? json_decode($sections_json, true) : [];

        $gallery = [];
        if (!empty($_FILES['images']['name'])) {
            $file_names = $_FILES['images']['name'];
            if (!is_array($file_names)) {
                $file_names = [$file_names];
                $_FILES['images']['type'] = [$_FILES['images']['type']];
                $_FILES['images']['tmp_name'] = [$_FILES['images']['tmp_name']];
                $_FILES['images']['error'] = [$_FILES['images']['error']];
                $_FILES['images']['size'] = [$_FILES['images']['size']];
            }

            $file_count = count($file_names);
            for ($i = 0; $i < $file_count; $i++) {
                $_FILES['image'] = [
                    'name' => $_FILES['images']['name'][$i],
                    'type' => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error' => $_FILES['images']['error'][$i],
                    'size' => $_FILES['images']['size'][$i]
                ];

                $upload = $this->uploadImage('image', './uploads/blog/gallery/');
                if ($upload) {
                    $gallery[] = [
                        // Stored relative; Blog_model expands it to the current host on read
                        'image_url' => $upload['relative_path'],
                        'file_name' => $upload['file_name'],
                        'alt_text' => '',
                        'sort_order' => $i
                    ];
                }
            }
        }

        // Handle main image selection
        $main_image_url = $this->input->post('main_image_url');
        $main_image_index = $this->input->post('main_image_index');
        $cover_image_url = null;

        // Determine which cover image to use
        if (!empty($main_image_url)) {
            // Resolve to the URL stored for this post (host-insensitive, so it
            // still matches after a domain move) and validate ownership
            $resolved = $this->blog_model->resolve_post_image_url($id, $main_image_url);
            if ($resolved === null) {
                http_response_code(400);
                echo json_encode([
                    'status' => 400,
                    'message' => 'main_image_url does not belong to this post'
                ]);
                return;
            }
            $cover_image_url = $resolved;
        } elseif (!empty($gallery) && $main_image_index !== null && $main_image_index !== '') {
            // Use index from newly uploaded images
            $index = intval($main_image_index);
            if ($index < 0 || $index >= count($gallery)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 400,
                    'message' => 'main_image_index must be a valid zero-based index into uploaded images. Provided: ' . $index . ', Available: ' . count($gallery),
                    'upload_errors' => $this->upload_errors
                ]);
                return;
            }
            $cover_image_url = $gallery[$index]['image_url'];
        } elseif (!empty($gallery)) {
            // Default: use first new image
            $cover_image_url = $gallery[0]['image_url'];
        }
        // else: preserve existing cover_image_url (passed as null to model)

        $post = $this->blog_model->update_post($id, $title, $category_id, $excerpt, $status, $sections, $gallery, $cover_image_url);

        echo json_encode([
            'status' => 200,
            'message' => 'Blog post updated successfully',
            'post' => $post
        ]);
    }

    public function delete_blog_post()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth(true)) {
            return;
        }

        $object = json_decode(file_get_contents('php://input'), true);
        $id = intval($object['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 400, 'message' => 'id is required']);
            return;
        }

        $this->blog_model->delete_post($id);

        echo json_encode([
            'status' => 200,
            'message' => 'Blog post deleted successfully'
        ]);
    }
}
