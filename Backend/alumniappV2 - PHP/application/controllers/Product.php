<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Product extends MY_Controller
{
    private $cached_request_body = NULL;    
    
    public function __construct()
    {                
        parent::__construct();

        $this->load->library('email');
        $this->load->library('ion_auth');        
        $this->load->model('api_model');
        $this->load->model('base_model');        
    }

    // ==========================================
    // PRODUCT SECTION
    // ==========================================

    // (Admin only)
    public function pin_product_item()
    {
        $user = $this->authorizeStoreAdmins();

        if (!$user) {
            return;
        }

        $object = json_decode(file_get_contents("php://input"), true);
        $productId = isset($object['product_id']) ? (int)$object['product_id'] : 0;

        if (!isset($object['pin_item'])) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'pin_item is required.'
                ]));
        }

        $pinItem = filter_var($object['pin_item'], FILTER_VALIDATE_BOOLEAN);

        if ($productId <= 0) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'Invalid product.'
                ]));
        }
        // Check product exists
        $product = $this->db
            ->where('id', $productId)
            ->where('status', 'active')
            ->get('products')
            ->row_array();

        if (!$product) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'Product not found.'
                ]));
        }

        /*
        ----------------------------------------
        Maximum of 4 pinned products
        ----------------------------------------
        */
        if ($pinItem) {
            // If already pinned, no need to count
            if (!$product['pin_item']) {
                $pinnedCount = $this->db
                    ->where('pin_item', 1)
                    ->where('status', 'active')
                    ->count_all_results('products');

                if ($pinnedCount >= 4) {
                    return $this->output
                        ->set_status_header(400)
                        ->set_content_type('application/json')
                        ->set_output(json_encode([
                            'status' => false,
                            'message' => 'You can only pin a maximum of 4 products. Please unpin one before pinning another.'
                        ]));
                }
            }
        }
        $updated = $this->db
            ->where('id', $productId)
            ->update('products', [
                'pin_item' => $pinItem ? 1 : 0
            ]);

        if (!$updated) {
            return $this->output
                ->set_status_header(500)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status' => false,
                    'message' => 'Unable to update product.'
                ]));
        }

        $pinnedCount = $this->db
            ->where('pin_item', 1)
            ->where('status', 'active')
            ->count_all_results('products');

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'message' => $pinItem
                    ? 'Product pinned successfully.'
                    : 'Product unpinned successfully.',
                'data' => [
                    'product_id' => $productId,
                    'pin_item' => (bool) $pinItem,
                    'total_pinned' => $pinnedCount,
                    'max_pinned' => 4
                ]
            ]));
    }

    public function fetch_products()
    {        
        // Intentionally uses API-key-only auth (no user login required to browse products)
        if (!$this->checkAPI_token_from_header()) {
            return $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'API key is invalid!']));
        }     

        // Fetch active products
        $products = $this->db
            ->where('status', 'active')
            ->order_by('pin_item', 'DESC')
            ->order_by('id', 'DESC')
            ->get('products')
            ->result_array();

        // No products
        if (empty($products)) {
            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status'  => true,
                    'message' => 'No products found.',
                    'data'    => []
                ]));
        }

        foreach ($products as &$product) {
            // Convert boolean fields
            $product['has_size']  = (bool)$product['has_size'];
            $product['has_color'] = (bool)$product['has_color'];
            $product['pin_item']  = (bool)$product['pin_item'];

            // Images
            $product['images'] = $this->db
                ->select('id, image_path, is_spotlight')
                ->where('product_id', $product['id'])
                ->order_by('is_spotlight', 'DESC')
                ->get('product_images')
                ->result_array();

            foreach ($product['images'] as &$image) {
                $image['is_spotlight'] = (bool)$image['is_spotlight'];
                $image['image_url'] = base_url($image['image_path']);
            }

            unset($image);

            // Variants
            $product['variants'] = $this->db
                ->select('id, color, size, quantity, image_id')
                ->where('product_id', $product['id'])
                ->order_by('color', 'ASC')
                ->order_by('size', 'ASC')
                ->get('product_variants')
                ->result_array();

            // Calculate total stock
            if ($product['has_size'] || $product['has_color']) {
                $totalStock = 0;
                foreach ($product['variants'] as $variant) {
                    $totalStock += (int)$variant['quantity'];
                }
                $product['total_stock'] = $totalStock;
            } else {
                $product['total_stock'] = (int)$product['quantity'];
            }
        }
        // Count pinned products
        $pinnedCount = $this->db
            ->where('pin_item', 1)
            ->where('status', 'active')
            ->count_all_results('products');
        
        unset($product);        
        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status' => true,
                'message' => 'Products retrieved successfully.', 
                'meta' => [
                    'total_products' => count($products),
                    'total_pinned' => $pinnedCount,
                    'max_pinned' => 4
                ],              
                'data' => $products
            ]));
    }    

    // (Admin only)
    public function add_product()
    {
        // ----------------------------------------
        // Authorize Request
        // ----------------------------------------
        $user = $this->authorizeStoreAdmins();

        if (!$user) {
            return;
        }        

        // ----------------------------------------
        // Validate Required Fields
        // ----------------------------------------
        $product_name = trim($this->input->post('product_name'));
        $category     = trim($this->input->post('category'));
        $price        = trim($this->input->post('price'));
        $description  = trim($this->input->post('description'));
        $has_size     = (int)$this->input->post('has_size');
        $has_color    = (int)$this->input->post('has_color');
        $quantity     = $this->input->post('quantity');

        if (
            empty($product_name) ||
            empty($category) ||
            $price === '' ||
            empty($description)
        ) {
            return $this->jsonResponse(400, false, 'Please fill all required fields.');
        }

        // Numeric validation for price
        if (!is_numeric($price) || (float)$price < 0) {
            return $this->jsonResponse(400, false, 'Price must be a valid non-negative number.');
        }
        $price = (float)$price;

        // Quantity only matters when there are no variants (has_size/has_color = 0)
        if (!$has_size && !$has_color) {
            if ($quantity === null || $quantity === '' || !is_numeric($quantity) || (int)$quantity < 0) {
                return $this->jsonResponse(400, false, 'Quantity must be a valid non-negative number.');
            }
            $quantity = (int)$quantity;
        } else {
            $quantity = null;
        }

        // ----------------------------------------
        // Decode & Validate Variants
        // ----------------------------------------
        $variants = [];

        if ($has_size || $has_color) {
            $variants = json_decode($this->input->post('variants'), true);
            if (!is_array($variants) || empty($variants)) {
                return $this->jsonResponse(400, false, 'Please provide at least one variant.');
            }

            // Validate each variant up front, before touching the DB or filesystem
            $seenCombos = [];
            foreach ($variants as $i => $variant) {
                $variantQty = $variant['quantity'] ?? null;
                if ($variantQty === null || !is_numeric($variantQty) || (int)$variantQty < 0) {
                    return $this->jsonResponse(400, false, "Invalid quantity for variant #{$i}.");
                }

                $color = !empty($variant['color']) ? trim($variant['color']) : null;
                $size  = !empty($variant['size']) ? trim($variant['size']) : null;

                if ($has_color && !$color) {
                    return $this->jsonResponse(400, false, "Variant #{$i} is missing a color.");
                }
                if ($has_size && !$size) {
                    return $this->jsonResponse(400, false, "Variant #{$i} is missing a size.");
                }

                // Duplicate color/size combo guard (DB has no unique constraint for this)
                $comboKey = ($color ?? '') . '|' . ($size ?? '');
                if (isset($seenCombos[$comboKey])) {
                    return $this->jsonResponse(400, false, "Duplicate variant combination at #{$i}.");
                }
                $seenCombos[$comboKey] = true;
            }
        }

        // ----------------------------------------
        // Validate Image Upload
        // ----------------------------------------
        if (empty($_FILES['images']['name'][0])) {
            return $this->jsonResponse(400, false, 'Please upload at least one product image.');
        }

        $imageCount = count($_FILES['images']['name']);

        // Validate spotlight_index and any variant image_index BEFORE uploading anything
        $spotlightIndex = (int)$this->input->post('spotlight_index');
        if ($spotlightIndex < 0 || $spotlightIndex >= $imageCount) {
            return $this->jsonResponse(400, false, 'Invalid spotlight index.');
        }

        foreach ($variants as $i => $variant) {
            if (isset($variant['image_index'])) {
                $idx = (int)$variant['image_index'];
                if ($idx < 0 || $idx >= $imageCount) {
                    return $this->jsonResponse(400, false, "Invalid image index for variant #{$i}.");
                }
            }
        }

        // ----------------------------------------
        // Upload Configuration
        // ----------------------------------------
        $uploadPath = FCPATH . 'uploads/products/';

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $config = [
            'upload_path'   => $uploadPath,
            'allowed_types' => 'jpg|jpeg|png|webp',
            'encrypt_name'  => TRUE,
            'max_size'      => 5120 // 5MB
        ];

        $this->load->library('upload');

        // ----------------------------------------
        // Begin Database Transaction
        // ----------------------------------------
        $this->db->trans_begin();

        // ----------------------------------------
        // Insert Product
        // ----------------------------------------
        $productData = [
            'user_id'      => $user->user_id,
            'product_name' => $product_name,
            'category'     => $category,
            'price'        => $price,
            'description'  => $description,
            'has_size'     => $has_size,
            'has_color'    => $has_color,
            'quantity'     => $quantity,
            'status'       => 'active'
        ];

        $this->db->insert('products', $productData);

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to create product.');
        }

        $productId = $this->db->insert_id();

        // ----------------------------------------
        // Upload Images
        // ----------------------------------------
        $uploadedImages = [];
        $files = $_FILES['images'];

        for ($i = 0; $i < $imageCount; $i++) {
            $_FILES['image']['name']     = $files['name'][$i];
            $_FILES['image']['type']     = $files['type'][$i];
            $_FILES['image']['tmp_name'] = $files['tmp_name'][$i];
            $_FILES['image']['error']    = $files['error'][$i];
            $_FILES['image']['size']     = $files['size'][$i];

            $this->upload->initialize($config);

            if (!$this->upload->do_upload('image')) {
                $this->cleanupImages($uploadedImages);
                $this->db->trans_rollback();
                return $this->jsonResponse(400, false, $this->upload->display_errors('', ''));
            }

            $uploadData = $this->upload->data();

            $uploadedImages[] = [
                'product_id'   => $productId,
                'image_path'   => 'uploads/products/' . $uploadData['file_name'],
                'is_spotlight' => ($i === $spotlightIndex) ? 1 : 0
            ];
        }

        // ----------------------------------------
        // Insert Product Images
        // ----------------------------------------
        $imageIdMap = [];

        foreach ($uploadedImages as $index => $image) {
            $this->db->insert('product_images', $image);

            if (!$this->db->affected_rows()) {
                $this->cleanupImages($uploadedImages);
                $this->db->trans_rollback();
                return $this->jsonResponse(500, false, 'Unable to save product images.');
            }

            $imageIdMap[$index] = $this->db->insert_id();
        }

        // ----------------------------------------
        // Insert Variants
        // ----------------------------------------
        foreach ($variants as $variant) {
            $color = !empty($variant['color']) ? trim($variant['color']) : null;
            $size  = !empty($variant['size']) ? trim($variant['size']) : null;

            $imageId = null;
            if (isset($variant['image_index'])) {
                // Bounds already validated above, but image upload could still
                // have failed earlier and rolled back before we get here, so
                // this stays defensive rather than assuming the key exists.
                $idx = (int)$variant['image_index'];
                $imageId = $imageIdMap[$idx] ?? null;
            }

            $variantData = [
                'product_id' => $productId,
                'color'      => $color,
                'size'       => $size,
                'quantity'   => (int)$variant['quantity'],
                'image_id'   => $imageId
            ];

            $this->db->insert('product_variants', $variantData);

            if (!$this->db->affected_rows()) {
                $this->cleanupImages($uploadedImages);
                $this->db->trans_rollback();
                return $this->jsonResponse(500, false, 'Unable to save product variants.');
            }
        }

        // ----------------------------------------
        // Commit Transaction
        // ----------------------------------------
        if ($this->db->trans_status() === FALSE) {
            $this->cleanupImages($uploadedImages);
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to create product.');
        }

        $this->db->trans_commit();

        return $this->jsonResponse(201, true, 'Product created successfully.', [
            'product_id' => $productId
        ]);
    }

    /**
     * Edit an existing product.
     * Expects multipart/form-data (POST), since new images may be attached. (Admin only)
     */
    public function edit_product()
    {
        // ----------------------------------------
        // Authorize Request
        // ----------------------------------------
        $user = $this->authorizeStoreAdmins();

        if (!$user) {
            return;
        }

        // ----------------------------------------
        // Validate Product Exists & Is Owned By This User
        // ----------------------------------------
        $productId = (int)$this->input->post('product_id');

        if (!$productId) {
            return $this->jsonResponse(400, false, 'product_id is required.');
        }

        $existingProduct = $this->db
            ->where('id', $productId)
            ->get('products')
            ->row_array();

        if (!$existingProduct) {
            return $this->jsonResponse(404, false, 'Product not found.');
        }

        // if ((int)$existingProduct['user_id'] !== (int)$user->user_id) {
        //     return $this->jsonResponse(403, false, 'You do not have permission to edit this product.');
        // }

        // ----------------------------------------
        // Validate Required Fields
        // ----------------------------------------
        $product_name = trim($this->input->post('product_name'));
        $category     = trim($this->input->post('category'));
        $price        = trim($this->input->post('price'));
        $description  = trim($this->input->post('description'));
        $has_size     = (int)$this->input->post('has_size');
        $has_color    = (int)$this->input->post('has_color');
        $quantity     = $this->input->post('quantity');

        if (
            empty($product_name) ||
            empty($category) ||
            $price === '' ||
            empty($description)
        ) {
            return $this->jsonResponse(400, false, 'Please fill all required fields.');
        }

        if (!is_numeric($price) || (float)$price < 0) {
            return $this->jsonResponse(400, false, 'Price must be a valid non-negative number.');
        }
        $price = (float)$price;

        if (!$has_size && !$has_color) {
            if ($quantity === null || $quantity === '' || !is_numeric($quantity) || (int)$quantity < 0) {
                return $this->jsonResponse(400, false, 'Quantity must be a valid non-negative number.');
            }
            $quantity = (int)$quantity;
        } else {
            $quantity = null;
        }

        // ----------------------------------------
        // Decode delete_image_ids
        // ----------------------------------------
        $deleteImageIds = [];
        $rawDeleteIds = $this->input->post('delete_image_ids');

        if (!empty($rawDeleteIds)) {
            $decoded = json_decode($rawDeleteIds, true);
            if (!is_array($decoded)) {
                return $this->jsonResponse(400, false, 'delete_image_ids must be a JSON array of ids.');
            }
            $deleteImageIds = array_map('intval', $decoded);
        }

        // Existing images currently on this product (before any deletion)
        $existingImages = $this->db
            ->where('product_id', $productId)
            ->get('product_images')
            ->result_array();

        $existingImageIds = array_column($existingImages, 'id');

        // Validate every id requested for deletion actually belongs to this product
        foreach ($deleteImageIds as $delId) {
            $existingImageIds = array_map('intval', $existingImageIds);
            if (!in_array($delId, $existingImageIds, true)) {
                return $this->jsonResponse(400, false, "Image id {$delId} does not belong to this product.");
            }
        }

        // How many images will remain after deletions, before any new uploads
        $remainingExistingIds = array_values(array_diff($existingImageIds, $deleteImageIds));

        // ----------------------------------------
        // New image uploads (optional on edit)
        // ----------------------------------------
        $hasNewImages = !empty($_FILES['images']['name'][0]);
        $newImageCount = $hasNewImages ? count($_FILES['images']['name']) : 0;

        // A product must end up with at least one image overall
        if (count($remainingExistingIds) === 0 && $newImageCount === 0) {
            return $this->jsonResponse(400, false, 'Product must have at least one image. Add a new image or keep an existing one.');
        }

        // ----------------------------------------
        // Decode & Validate Variants (full replace)
        // ----------------------------------------
        $variants = [];

        if ($has_size || $has_color) {
            $variants = json_decode($this->input->post('variants'), true);

            if (!is_array($variants) || empty($variants)) {
                return $this->jsonResponse(400, false, 'Please provide at least one variant.');
            }

            $seenCombos = [];
            foreach ($variants as $i => $variant) {
                $variantQty = $variant['quantity'] ?? null;
                if ($variantQty === null || !is_numeric($variantQty) || (int)$variantQty < 0) {
                    return $this->jsonResponse(400, false, "Invalid quantity for variant #{$i}.");
                }

                $color = !empty($variant['color']) ? trim($variant['color']) : null;
                $size  = !empty($variant['size']) ? trim($variant['size']) : null;

                if ($has_color && !$color) {
                    return $this->jsonResponse(400, false, "Variant #{$i} is missing a color.");
                }
                if ($has_size && !$size) {
                    return $this->jsonResponse(400, false, "Variant #{$i} is missing a size.");
                }

                $comboKey = ($color ?? '') . '|' . ($size ?? '');
                if (isset($seenCombos[$comboKey])) {
                    return $this->jsonResponse(400, false, "Duplicate variant combination at #{$i}.");
                }
                $seenCombos[$comboKey] = true;

                // A variant may reference EITHER an existing kept image OR a new upload index — not both
                $hasImageId = isset($variant['image_id']) && $variant['image_id'] !== '';
                $hasImageIndex = isset($variant['image_index']) && $variant['image_index'] !== '';

                if ($hasImageId && $hasImageIndex) {
                    return $this->jsonResponse(400, false, "Variant #{$i} cannot set both image_id and image_index.");
                }                

                if ($hasImageId) {
                    $refId = (int)$variant['image_id'];
                    $remainingExistingIds = array_map('intval', $remainingExistingIds);
                    if (!in_array($refId, $remainingExistingIds, true)) {
                        return $this->jsonResponse(400, false, "Variant #{$i} references an image_id that is not a kept existing image.");
                    }
                }

                if ($hasImageIndex) {
                    $idx = (int)$variant['image_index'];
                    if ($idx < 0 || $idx >= $newImageCount) {
                        return $this->jsonResponse(400, false, "Variant #{$i} references an invalid new image_index.");
                    }
                }
            }
        }

        // ----------------------------------------
        // Validate spotlight selection
        // ----------------------------------------
        $spotlightImageId = $this->input->post('spotlight_image_id');
        $spotlightIndex    = $this->input->post('spotlight_index');

        if ($spotlightImageId !== null && $spotlightImageId !== '' && $spotlightIndex !== null && $spotlightIndex !== '') {
            return $this->jsonResponse(400, false, 'Set only one of spotlight_image_id or spotlight_index, not both.');
        }

        if ($spotlightImageId !== null && $spotlightImageId !== '') {
            $spotlightImageId = (int)$spotlightImageId;
            $remainingExistingIds = array_map('intval', $remainingExistingIds);
            if (!in_array($spotlightImageId, $remainingExistingIds, true)) {
                return $this->jsonResponse(400, false, 'spotlight_image_id must be a kept existing image.');
            }
        } else {
            $spotlightImageId = null;
        }

        if ($spotlightIndex !== null && $spotlightIndex !== '') {
            $spotlightIndex = (int)$spotlightIndex;
            if ($spotlightIndex < 0 || $spotlightIndex >= $newImageCount) {
                return $this->jsonResponse(400, false, 'spotlight_index is out of range for the newly uploaded images.');
            }
        } else {
            $spotlightIndex = null;
        }

        // If neither spotlight option given, we keep whatever is currently the spotlight
        // (as long as it isn't one of the deleted images — handled below).

        // ----------------------------------------
        // Upload Configuration (only used if new images are present)
        // ----------------------------------------
        $uploadPath = FCPATH . 'uploads/products/';

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        $config = [
            'upload_path'   => $uploadPath,
            'allowed_types' => 'jpg|jpeg|png|webp',
            'encrypt_name'  => TRUE,
            'max_size'      => 5120
        ];

        $this->load->library('upload');

        // ----------------------------------------
        // Begin Transaction
        // ----------------------------------------
        $this->db->trans_begin();

        // Track newly uploaded files so we can clean them up on failure.
        // Files belonging to deleted existing images are tracked separately
        // and only removed AFTER the transaction commits successfully.
        $newlyUploadedFiles = [];
        $filesPendingDeletion = [];

        // ----------------------------------------
        // Update Product Row
        // ----------------------------------------
        $productData = [
            'product_name' => $product_name,
            'category'     => $category,
            'price'        => $price,
            'description'  => $description,
            'has_size'     => $has_size,
            'has_color'    => $has_color,
            'quantity'     => $quantity
        ];

        $this->db->where('id', $productId)->update('products', $productData);

        // Note: update() can legitimately report 0 affected rows if nothing
        // actually changed value-wise. We only treat a DB error as fatal here,
        // which trans_status() (checked at commit time) will catch.

        // ----------------------------------------
        // Delete Requested Existing Images
        // ----------------------------------------
        if (!empty($deleteImageIds)) {
            $deleteImageIds = array_map('intval', $deleteImageIds);
            $imagesToDelete = array_filter($existingImages, function ($img) use ($deleteImageIds) {
                return in_array((int)$img['id'], $deleteImageIds, true);
            });

            $this->db->where_in('id', $deleteImageIds)->delete('product_images');

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return $this->jsonResponse(500, false, 'Unable to remove selected images.');
            }

            // Defer actual file deletion until after commit succeeds
            foreach ($imagesToDelete as $img) {
                $filesPendingDeletion[] = $img['image_path'];
            }
        }

        // ----------------------------------------
        // Upload New Images
        // ----------------------------------------
        $newImageIdMap = []; // index (within this request's uploads) => new product_images.id

        if ($newImageCount > 0) {
            $files = $_FILES['images'];

            for ($i = 0; $i < $newImageCount; $i++) {
                $_FILES['image']['name']     = $files['name'][$i];
                $_FILES['image']['type']     = $files['type'][$i];
                $_FILES['image']['tmp_name'] = $files['tmp_name'][$i];
                $_FILES['image']['error']    = $files['error'][$i];
                $_FILES['image']['size']     = $files['size'][$i];

                $this->upload->initialize($config);

                if (!$this->upload->do_upload('image')) {
                    $this->cleanupImages($newlyUploadedFiles);
                    $this->db->trans_rollback();
                    return $this->jsonResponse(400, false, $this->upload->display_errors('', ''));
                }

                $uploadData = $this->upload->data();
                $relativePath = 'uploads/products/' . $uploadData['file_name'];

                $newlyUploadedFiles[] = ['image_path' => $relativePath];

                $this->db->insert('product_images', [
                    'product_id'   => $productId,
                    'image_path'   => $relativePath,
                    'is_spotlight' => 0 // resolved in the spotlight step below
                ]);

                if (!$this->db->affected_rows()) {
                    $this->cleanupImages($newlyUploadedFiles);
                    $this->db->trans_rollback();
                    return $this->jsonResponse(500, false, 'Unable to save new product images.');
                }

                $newImageIdMap[$i] = $this->db->insert_id();
            }
        }

        // ----------------------------------------
        // Resolve Spotlight
        // ----------------------------------------
        // Clear spotlight on all current images for this product, then set the one requested.
        $this->db->where('product_id', $productId)->update('product_images', ['is_spotlight' => 0]);

        $finalSpotlightId = null;

        if ($spotlightImageId !== null) {
            $finalSpotlightId = $spotlightImageId;
        } elseif ($spotlightIndex !== null) {
            $finalSpotlightId = $newImageIdMap[$spotlightIndex] ?? null;
        } else {
            // No explicit spotlight given: keep the previous spotlight if it still exists,
            // otherwise fall back to the first remaining/newly uploaded image.
            $previousSpotlight = array_filter($existingImages, function ($img) {
                return (int)$img['is_spotlight'] === 1;
            });
            $previousSpotlight = reset($previousSpotlight);
            $remainingExistingIds = array_map('intval', $remainingExistingIds);
            if ($previousSpotlight && in_array((int)$previousSpotlight['id'], $remainingExistingIds, true)) {
                $finalSpotlightId = (int)$previousSpotlight['id'];
            } elseif (!empty($remainingExistingIds)) {
                $finalSpotlightId = $remainingExistingIds[0];
            } elseif (!empty($newImageIdMap)) {
                $finalSpotlightId = $newImageIdMap[0];
            }
        }

        if ($finalSpotlightId !== null) {
            $this->db->where('id', $finalSpotlightId)->update('product_images', ['is_spotlight' => 1]);
        }

        // ----------------------------------------
        // Replace Variants
        // ----------------------------------------
        $this->db->where('product_id', $productId)->delete('product_variants');

        foreach ($variants as $variant) {
            $color = !empty($variant['color']) ? trim($variant['color']) : null;
            $size  = !empty($variant['size']) ? trim($variant['size']) : null;

            $imageId = null;
            if (isset($variant['image_id']) && $variant['image_id'] !== '') {
                $imageId = (int)$variant['image_id'];
            } elseif (isset($variant['image_index']) && $variant['image_index'] !== '') {
                $idx = (int)$variant['image_index'];
                $imageId = $newImageIdMap[$idx] ?? null;
            }

            $variantData = [
                'product_id' => $productId,
                'color'      => $color,
                'size'       => $size,
                'quantity'   => (int)$variant['quantity'],
                'image_id'   => $imageId
            ];

            $this->db->insert('product_variants', $variantData);

            if (!$this->db->affected_rows()) {
                $this->cleanupImages($newlyUploadedFiles);
                $this->db->trans_rollback();
                return $this->jsonResponse(500, false, 'Unable to save product variants.');
            }
        }

        // If product no longer uses variants, make sure none remain orphaned
        // (already handled above by the unconditional delete + conditional re-insert)

        // ----------------------------------------
        // Commit
        // ----------------------------------------
        if ($this->db->trans_status() === FALSE) {
            $this->cleanupImages($newlyUploadedFiles);
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to update product.');
        }

        $this->db->trans_commit();

        // Only remove physical files for deleted images AFTER a successful commit,
        // so a rollback never leaves the DB referencing a file we already deleted.
        if (!empty($filesPendingDeletion)) {
            foreach ($filesPendingDeletion as $path) {
                $fullPath = FCPATH . $path;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }

        return $this->jsonResponse(200, true, 'Product updated successfully.', [
            'product_id' => $productId
        ]);
    }

    /**
     * Permanently delete a product: removes the product row, all its images
     * (DB rows + physical files), and all its variants (via FK cascade).
     *
     * Expects POST (or DELETE, if your routing supports it) with:
     *   product_id (required) - (Admin only)
     */
    public function delete_product()
    {
        // ----------------------------------------
        // Authorize Request
        // ----------------------------------------
        $user = $this->authorizeStoreAdmins();

        if (!$user) {
            return;
        }

        // ----------------------------------------
        // Validate Product Exists & Is Owned By This User
        // ----------------------------------------
        $productId = (int)$this->input->post('product_id');

        if (!$productId) {
            return $this->jsonResponse(400, false, 'product_id is required.');
        }

        $existingProduct = $this->db
            ->where('id', $productId)
            ->get('products')
            ->row_array();

        if (!$existingProduct) {
            return $this->jsonResponse(404, false, 'Product not found.');
        }

        // if ((int)$existingProduct['user_id'] !== (int)$user->user_id) {
        //     return $this->jsonResponse(403, false, 'You do not have permission to delete this product.');
        // }

        // ----------------------------------------
        // Collect image paths BEFORE deleting rows
        // ----------------------------------------
        $images = $this->db
            ->select('image_path')
            ->where('product_id', $productId)
            ->get('product_images')
            ->result_array();

        // ----------------------------------------
        // Begin Transaction
        // ----------------------------------------
        $this->db->trans_begin();

        // product_variants has ON DELETE CASCADE on product_id, and
        // product_images also has ON DELETE CASCADE on product_id, so deleting
        // the product row alone is enough at the DB level. We still delete
        // explicitly first for clarity and to avoid relying on cascade behavior
        // being configured correctly in every environment.
        $this->db->where('product_id', $productId)->delete('product_variants');
        $this->db->where('product_id', $productId)->delete('product_images');
        $this->db->where('id', $productId)->delete('products');

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to delete product.');
        }

        $this->db->trans_commit();

        // Remove physical files only after the DB transaction succeeds
        foreach ($images as $img) {
            $path = FCPATH . $img['image_path'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        return $this->jsonResponse(200, true, 'Product deleted successfully.');
    }

    // ==========================================
    // CART SECTION
    // ==========================================

    /**
     * Shared cart builder — returns a fully assembled cart array for a given user.
     * Returns an empty cart shape if the user has no active cart.
     */
    protected function getCart($userId)
    {
        $cart = $this->db
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->get('carts')
            ->row_array();

        if (!$cart) {
            return [
                'cart_id'     => null,
                'total_items' => 0,
                'subtotal'    => '0.00',
                'items'       => []
            ];
        }

        $items = $this->db
            ->select("
                cart_items.id          AS cart_item_id,
                cart_items.product_id,
                cart_items.variant_id,
                cart_items.unit_price,
                cart_items.quantity,
                products.product_name,
                product_variants.color,
                product_variants.size,
                COALESCE(variant_image.id, product_image.id) AS image_id,
                COALESCE(variant_image.image_path, product_image.image_path) AS image_path
            ")
            ->from('cart_items')
            ->join('products', 'products.id = cart_items.product_id')
            ->join('product_variants', 'product_variants.id = cart_items.variant_id', 'left')
             // Image for variant
            ->join(
                'product_images AS variant_image',
                'variant_image.id = product_variants.image_id',
                'left'
            )
            // Default image for product when no variant exists
            ->join(
                'product_images AS product_image',
                'product_image.product_id = cart_items.product_id AND product_image.is_spotlight = 1',
                'left'
            )
            ->where('cart_items.cart_id', $cart['id'])
            ->get()
            ->result_array();

        $subtotal   = 0;
        $totalItems = 0;

        foreach ($items as &$item) {
            $itemSubtotal = $item['unit_price'] * $item['quantity'];
            $subtotal    += $itemSubtotal;
            $totalItems  += $item['quantity'];

            $item['subtotal'] = number_format($itemSubtotal, 2, '.', '');
            $item['variant']  = $item['variant_id']
                ? ['id' => $item['variant_id'], 'color' => $item['color'], 'size' => $item['size']]
                : null;
            $item['image'] = $item['image_id']
                ? ['id' => $item['image_id'], 'image_url' => base_url($item['image_path'])]
                : null;

            unset($item['variant_id'], $item['color'], $item['size'], $item['image_id'], $item['image_path']);
        }
        unset($item);

        return [
            'cart_id'     => (int)$cart['id'],
            'total_items' => $totalItems,
            'subtotal'    => number_format($subtotal, 2, '.', ''),
            'items'       => $items
        ];
    }

    public function fetch_cart()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => true,
                'message' => 'Cart retrieved successfully.',
                'data'    => $this->getCart($user->user_id)
            ]));
    }

    public function add_to_cart()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body      = json_decode(file_get_contents('php://input'), true);
        $productId = isset($body['product_id']) ? (int)$body['product_id'] : 0;
        $variantId = !empty($body['variant_id']) ? (int)$body['variant_id'] : null;
        $quantity  = isset($body['quantity']) ? (int)$body['quantity'] : 1;

        if ($productId <= 0 || $quantity <= 0) {
            return $this->jsonResponse(400, false, 'Invalid request.');
        }

        // ----------------------------------------
        // Validate Product
        // ----------------------------------------
        $product = $this->db
            ->where('id', $productId)
            ->where('status', 'active')
            ->get('products')
            ->row_array();

        if (!$product) {
            return $this->jsonResponse(404, false, 'Product not found.');
        }

        // ----------------------------------------
        // Validate Variant & Stock
        // ----------------------------------------
        $variant   = null; // initialised here so it's always defined
        $unitPrice = (float)$product['price'];

        if ($product['has_color'] || $product['has_size']) {
            if (!$variantId) {
                return $this->jsonResponse(400, false, 'Please select a product variant.');
            }

            $variant = $this->db
                ->where('id', $variantId)
                ->where('product_id', $productId)
                ->get('product_variants')
                ->row_array();

            if (!$variant) {
                return $this->jsonResponse(404, false, 'Variant not found.');
            }

            if ((int)$variant['quantity'] < $quantity) {
                return $this->jsonResponse(400, false, 'Insufficient stock.');
            }
        } else {
            if ((int)$product['quantity'] < $quantity) {
                return $this->jsonResponse(400, false, 'Insufficient stock.');
            }
        }

        // ----------------------------------------
        // Find or Create Cart (wrapped in transaction
        // so a failed cart_items insert doesn't leave
        // an empty orphaned carts row)
        // ----------------------------------------
        $this->db->trans_begin();

        $cart = $this->db
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->get('carts')
            ->row_array();

        if (!$cart) {
            $this->db->insert('carts', [
                'user_id' => $user->user_id,
                'status'  => 'active'
            ]);

            if (!$this->db->affected_rows()) {
                $this->db->trans_rollback();
                return $this->jsonResponse(500, false, 'Unable to create cart.');
            }

            $cartId = $this->db->insert_id();
        } else {
            $cartId = (int)$cart['id'];
        }

        // ----------------------------------------
        // Check if Item Already In Cart
        // ----------------------------------------
        $this->db->where('cart_id', $cartId)->where('product_id', $productId);
        if ($variantId) {
            $this->db->where('variant_id', $variantId);
        } else {
            $this->db->where('variant_id IS NULL', null, false);
        }
        $existing = $this->db->get('cart_items')->row_array();

        if ($existing) {
            $newQuantity = (int)$existing['quantity'] + $quantity;

            // Re-check stock against the new cumulative quantity
            $stockLimit = ($product['has_color'] || $product['has_size'])
                ? (int)$variant['quantity']
                : (int)$product['quantity'];

            if ($newQuantity > $stockLimit) {
                $this->db->trans_rollback();
                return $this->jsonResponse(400, false, 'Cannot add more than available stock.');
            }

            $this->db->where('id', $existing['id'])->update('cart_items', [
                'quantity'   => $newQuantity,
                    'line_total' => $unitPrice * $newQuantity
                ]);
        } else {
            $this->db->insert('cart_items', [
                'cart_id'    => $cartId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'unit_price' => $unitPrice,                
                'quantity'   => $quantity,
                'line_total' => $unitPrice * $quantity
            ]);
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to add item to cart.');
        }

        $this->db->trans_commit();

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => true,
                'message' => 'Item added to cart.',
                'data'    => $this->getCart($user->user_id)
            ]));
    }

    public function update_cart()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body       = json_decode(file_get_contents('php://input'), true);
        $cartItemId = isset($body['cart_item_id']) ? (int)$body['cart_item_id'] : 0;
        $quantity   = isset($body['quantity']) ? (int)$body['quantity'] : -1;

        if ($cartItemId <= 0) {
            return $this->jsonResponse(400, false, 'Invalid cart item.');
        }

        // quantity = 0 is treated as "remove this item"
        if ($quantity === 0) {
            return $this->_removeCartItem($cartItemId, $user->user_id);
        }

        if ($quantity < 0) {
            return $this->jsonResponse(400, false, 'Quantity must be 0 or greater.');
        }

        // ----------------------------------------
        // Fetch Cart Item With Full Context
        // ----------------------------------------
        $cartItem = $this->db
            ->select("
                cart_items.*,
                carts.user_id,
                products.has_color,
                products.has_size,
                products.quantity    AS product_quantity,
                product_variants.quantity AS variant_quantity
            ")
            ->from('cart_items')
            ->join('carts',            'carts.id            = cart_items.cart_id')
            ->join('products',         'products.id         = cart_items.product_id')
            ->join('product_variants', 'product_variants.id = cart_items.variant_id', 'left')
            ->where('cart_items.id', $cartItemId)
            ->get()
            ->row_array();

        if (!$cartItem) {
            return $this->jsonResponse(404, false, 'Cart item not found.');
        }

        if ((int)$cartItem['user_id'] !== (int)$user->user_id) {
            return $this->jsonResponse(403, false, 'Unauthorized.');
        }

        // ----------------------------------------
        // Check Stock
        // ----------------------------------------
        $stockLimit = ($cartItem['has_color'] || $cartItem['has_size'])
            ? (int)$cartItem['variant_quantity']
            : (int)$cartItem['product_quantity'];

        if ($quantity > $stockLimit) {
            return $this->jsonResponse(400, false, "Only {$stockLimit} item(s) available in stock.");
        }

        // ----------------------------------------
        // Update
        // ----------------------------------------
        $this->db->where('id', $cartItemId)->update('cart_items', [
            'quantity'   => $quantity,
            'line_total' => $cartItem['unit_price'] * $quantity
        ]);

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => true,
                'message' => 'Cart updated successfully.',
                'data'    => $this->getCart($user->user_id)
            ]));
    }

    public function remove_from_cart()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body       = json_decode(file_get_contents('php://input'), true);
        $cartItemId = isset($body['cart_item_id']) ? (int)$body['cart_item_id'] : 0;

        if ($cartItemId <= 0) {
            return $this->jsonResponse(400, false, 'Invalid cart item.');
        }

        return $this->_removeCartItem($cartItemId, $user->user_id);
    }

    /**
     * Internal helper used by both remove_from_cart() and update_cart() (when quantity = 0).
     * Verifies ownership, deletes the item, and returns the updated cart.
     */
    private function _removeCartItem(int $cartItemId, int $userId)
    {
        $cartItem = $this->db
            ->select('cart_items.id, carts.user_id')
            ->from('cart_items')
            ->join('carts', 'carts.id = cart_items.cart_id')
            ->where('cart_items.id', $cartItemId)
            ->where('carts.status', 'active')
            ->get()
            ->row_array();

        if (!$cartItem) {
            return $this->jsonResponse(404, false, 'Cart item not found.');
        }

        if ((int)$cartItem['user_id'] !== $userId) {
            return $this->jsonResponse(403, false, 'Unauthorized.');
        }

        $this->db->where('id', $cartItemId)->delete('cart_items');

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => true,
                'message' => 'Item removed from cart.',
                'data'    => $this->getCart($userId)
            ]));
    }

    public function clear_cart()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $cart = $this->db
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->get('carts')
            ->row_array();

        if (!$cart) {
            return $this->output
                ->set_status_header(200)
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'status'  => true,
                    'message' => 'Cart is already empty.',
                    'data'    => [
                        'cart_id'     => null,
                        'total_items' => 0,
                        'subtotal'    => '0.00',
                        'items'       => []
                    ]
                ]));
        }

        // Delete all items AND the cart row itself so getCart() returns
        // cart_id = null for a truly empty state (consistent with no-cart-yet).
        $this->db->trans_begin();

        $this->db->where('cart_id', $cart['id'])->delete('cart_items');
        $this->db->where('id', $cart['id'])->delete('carts');

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to clear cart.');
        }

        $this->db->trans_commit();

        return $this->output
            ->set_status_header(200)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'status'  => true,
                'message' => 'Cart cleared successfully.',
                'data'    => [
                    'cart_id'     => null,
                    'total_items' => 0,
                    'subtotal'    => '0.00',
                    'items'       => []
                ]
            ]));
    }

    // ==========================================
    // ADDRESS SECTION
    // ==========================================

    /**
     * Add a new saved delivery address for the authenticated user.
     * Expects: application/json
     */
    public function add_address()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $firstName       = trim($body['first_name'] ?? '');
        $lastName        = trim($body['last_name'] ?? '');
        $phone           = trim($body['phone'] ?? '');
        $additionalPhone = trim($body['additional_phone'] ?? '');
        $address         = trim($body['address'] ?? '');
        $landmark        = trim($body['landmark'] ?? '');
        $state           = trim($body['state'] ?? '');
        $area            = trim($body['area'] ?? '');        

        if (empty($firstName) || empty($lastName) || empty($phone) || empty($address) || empty($state) || empty($area)) {
            return $this->jsonResponse(400, false, 'Please fill all required address fields.');
        }

        $this->db->trans_begin();

        // Remove the default flag from all existing addresses
        $this->db
            ->where('user_id', $user->user_id)
            ->update('user_addresses', [
                'is_default' => 0
            ]);

        $this->db->insert('user_addresses', [
            'user_id'          => $user->user_id,
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'phone'            => $phone,
            'additional_phone' => $additionalPhone ?: null,
            'address'          => $address,
            'landmark'         => $landmark ?: null,
            'state'            => $state,
            'area'             => $area,
            'is_default'       => 1,
        ]);

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to save address.');
        }
        if ($this->db->trans_status() === FALSE) {
           $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to save address.');
        }

        $addressId = $this->db->insert_id();

        // Get the latest addresses
        $addresses = $this->db
            ->where('user_id', $user->user_id)
            ->order_by('id', 'DESC')
            ->get('user_addresses')
            ->result_array();

        foreach ($addresses as &$addr) {
            $addr['is_default'] = (bool)$addr['is_default'];
        }
        unset($addr);

        $this->db->trans_commit();

        return $this->jsonResponse(201, true, 'Address saved successfully.', [
            'address_id' => $addressId,
            'data'       => $addresses
        ]);
    }

    /**
     * Fetch all saved addresses for the authenticated user.
     * Default address is returned first.
     */
    public function fetch_addresses()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $addresses = $this->db
            ->where('user_id', $user->user_id)            
            ->order_by('id', 'DESC')
            ->get('user_addresses')
            ->result_array();

        foreach ($addresses as &$addr) {
            $addr['is_default'] = (bool)$addr['is_default'];
        }
        unset($addr);

        return $this->jsonResponse(200, true, 'Addresses retrieved successfully.', [
            'data' => $addresses
        ]);
    }

    /**
     * Edit an existing saved delivery address for the authenticated user.
     * Expects: application/json
     */
    public function edit_address()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        $id              = trim($body['id'] ?? '');
        $firstName       = trim($body['first_name'] ?? '');
        $lastName        = trim($body['last_name'] ?? '');
        $phone           = trim($body['phone'] ?? '');
        $additionalPhone = trim($body['additional_phone'] ?? '');
        $address         = trim($body['address'] ?? '');
        $landmark        = trim($body['landmark'] ?? '');
        $state           = trim($body['state'] ?? '');
        $area            = trim($body['area'] ?? '');        

        if (empty($id) || empty($firstName) || empty($lastName) || empty($phone) || empty($address) || empty($state) || empty($area)) {
            return $this->jsonResponse(400, false, 'Please fill all required address fields.');
        }

        $this->db->trans_begin();
        
        $this->db->where('id', $id);
        $this->db->where('user_id', $user->user_id);
        $this->db->update('user_addresses', [
            'first_name'       => $firstName,
            'last_name'        => $lastName,
            'phone'            => $phone,
            'additional_phone' => $additionalPhone ?: null,
            'address'          => $address,
            'landmark'         => $landmark ?: null,
            'state'            => $state,
            'area'             => $area,
        ]);

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to edit address.');
        }
        if ($this->db->trans_status() === FALSE) {
           $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to edit address.');
        }

        // Get the latest addresses
        $addresses = $this->db
            ->where('user_id', $user->user_id)
            ->order_by('id', 'DESC')
            ->get('user_addresses')
            ->result_array();       

        $this->db->trans_commit();

        return $this->jsonResponse(201, true, 'Address edited successfully.', [
            'address_id' => $id,
            'data'       => $addresses
        ]);
    }

    /**
     * Delete a saved address.
     * Expects: application/json { "address_id": 3 }
     */
    public function delete_address()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body      = json_decode(file_get_contents('php://input'), true);
        $addressId = (int)($body['address_id'] ?? 0);

        if (!$addressId) {
            return $this->jsonResponse(400, false, 'address_id is required.');
        }

        $address = $this->db
            ->where('id', $addressId)
            ->where('user_id', $user->user_id)
            ->get('user_addresses')
            ->row_array();

        if (!$address) {
            return $this->jsonResponse(404, false, 'Address not found.');
        }

        $this->db->where('id', $addressId)->delete('user_addresses');

        return $this->jsonResponse(200, true, 'Address deleted successfully.');
    }

    /**
     * Set an address as the default.
     * Expects: application/json { "address_id": 3 }
     */
    public function set_default_address()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body      = json_decode(file_get_contents('php://input'), true);
        $addressId = (int)($body['address_id'] ?? 0);

        if (!$addressId) {
            return $this->jsonResponse(400, false, 'address_id is required.');
        }

        $address = $this->db
            ->where('id', $addressId)
            ->where('user_id', $user->user_id)
            ->get('user_addresses')
            ->row_array();

        if (!$address) {
            return $this->jsonResponse(404, false, 'Address not found.');
        }

        $this->db->trans_begin();

        // Unset all, then set this one
        $this->db->where('user_id', $user->user_id)->update('user_addresses', ['is_default' => 0]);
        $this->db->where('id', $addressId)->update('user_addresses', ['is_default' => 1]);

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to update default address.');
        }

        $this->db->trans_commit();

        return $this->jsonResponse(200, true, 'Default address updated.');
    }

    /**
     * Fetch all delivery zones (states and areas with their shipping fees).
     * Useful for the frontend to populate the state/area dropdowns
     * and show the shipping fee preview before checkout.
     */
    public function fetch_delivery_zones()
    {
        if (!$this->checkAPI_token_from_header()) {
            return $this->output
                ->set_status_header(401)
                ->set_content_type('application/json')
                ->set_output(json_encode(['status' => false, 'message' => 'API key is invalid!']));
        }

        $zones = $this->db
            ->order_by('state', 'ASC')
            ->order_by('area', 'ASC')
            ->get('delivery_zones')
            ->result_array();

        // Group by state for easier frontend consumption
        $grouped = [];
        foreach ($zones as $zone) {
            $state = $zone['state'];
            if (!isset($grouped[$state])) {
                $grouped[$state] = [
                    'state'      => $state,
                    'areas'      => []
                ];
            }        
            $grouped[$state]['areas'][] = [
                'area' => $zone['area'],
                'fee'  => $zone['fee'],
            ];
        }

        return $this->jsonResponse(200, true, 'Delivery zones retrieved.', [
            'data' => array_values($grouped)
        ]);
    }

    // ==========================================
    // CHECKOUT SECTION
    // ==========================================

    /**
     * Initiate checkout.
     *
     * Validates cart, resolves delivery address + shipping fee,
     * creates a PENDING order (with snapshotted items),
     * initializes a Paystack transaction, and returns the
     * access_code for the frontend to open the inline popup.
     *     
     */
    public function initiate_checkout()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body         = json_decode(file_get_contents('php://input'), true);
        $deliveryType = trim($body['delivery_type'] ?? '');

        if (!in_array($deliveryType, ['door_delivery', 'self_pickup'], true)) {
            return $this->jsonResponse(400, false, 'delivery_type must be "door_delivery" or "self_pickup".');
        }

        // ----------------------------------------
        // Resolve delivery address + shipping fee
        // ----------------------------------------
        $firstName       = null;
        $lastName        = null;
        $phone           = null;
        $additionalPhone = null;
        $address         = null;
        $landmark        = null;
        $state           = null;
        $area            = null;
        $shippingFee     = 0.00;

        // Cancel any previous pending orders for this user
        $this->db
            ->where('user_id', $user->user_id)
            ->where('status', 'pending')
            ->update('orders', [
                'status'     => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

        if ($deliveryType === 'door_delivery') {
            $addressId = !empty($body['address_id']) ? (int)$body['address_id'] : null;

            if ($addressId) {
                // Use a saved address
                $savedAddress = $this->db
                    ->where('id', $addressId)
                    ->where('user_id', $user->user_id)
                    ->get('user_addresses')
                    ->row_array();

                if (!$savedAddress) {
                    return $this->jsonResponse(404, false, 'Saved address not found.');
                }

                $firstName       = $savedAddress['first_name'];
                $lastName        = $savedAddress['last_name'];
                $phone           = $savedAddress['phone'];
                $additionalPhone = $savedAddress['additional_phone'];
                $address         = $savedAddress['address'];
                $landmark        = $savedAddress['landmark'];
                $state           = $savedAddress['state'];
                $area            = $savedAddress['area'];

            } else {
                // Inline address
                $firstName       = trim($body['first_name'] ?? '');
                $lastName        = trim($body['last_name'] ?? '');
                $phone           = trim($body['phone'] ?? '');
                $additionalPhone = trim($body['additional_phone'] ?? '');
                $address         = trim($body['address'] ?? '');
                $landmark        = trim($body['landmark'] ?? '');
                $state           = trim($body['state'] ?? '');
                $area            = trim($body['area'] ?? '');

                if (empty($firstName) || empty($lastName) || empty($phone) || empty($address) || empty($state) || empty($area)) {
                    return $this->jsonResponse(400, false, 'Please provide all required delivery details.');
                }
            }

            // Look up shipping fee — specific area first, then state fallback
            $zone = $this->db
                ->where('state', $state)
                ->where('area', $area)
                ->get('delivery_zones')
                ->row_array();

            if (!$zone) {
                // Try state-level fallback (area IS NULL)
                $zone = $this->db
                    ->where('state', $state)
                    ->where('area IS NULL', null, false)
                    ->get('delivery_zones')
                    ->row_array();
            }

            if (!$zone) {
                return $this->jsonResponse(400, false,
                    "Delivery is not available to {$area}, {$state} yet. Please choose a different area or select Self Pickup."
                );
            }

            $shippingFee = (float)$zone['fee'];
        }

        // ----------------------------------------
        // Load active cart
        // ----------------------------------------
        $cart = $this->db
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->get('carts')
            ->row_array();

        if (!$cart) {
            return $this->jsonResponse(400, false, 'Your cart is empty.');
        }

        $cartItems = $this->db
            ->select("
                cart_items.id            AS cart_item_id,
                cart_items.product_id,
                cart_items.variant_id,
                cart_items.unit_price,
                cart_items.quantity,
                cart_items.line_total,
                products.product_name,
                products.status          AS product_status,
                products.has_size,
                products.has_color,
                products.quantity        AS product_stock,
                product_variants.color,
                product_variants.size,
                product_variants.quantity AS variant_stock
            ")
            ->from('cart_items')
            ->join('products',         'products.id         = cart_items.product_id')
            ->join('product_variants', 'product_variants.id = cart_items.variant_id', 'left')
            ->where('cart_items.cart_id', $cart['id'])
            ->get()
            ->result_array();

        if (empty($cartItems)) {
            return $this->jsonResponse(400, false, 'Your cart is empty.');
        }

        // ----------------------------------------
        // Validate stock for every item
        // ----------------------------------------
        $subtotal = 0;

        foreach ($cartItems as $item) {
            if ($item['product_status'] !== 'active') {
                return $this->jsonResponse(400, false,
                    "'{$item['product_name']}' is no longer available. Please remove it from your cart."
                );
            }

            if ($item['has_size'] || $item['has_color']) {
                if (!$item['variant_id']) {
                    return $this->jsonResponse(400, false,
                        "'{$item['product_name']}' requires a variant selection."
                    );
                }
                if ((int)$item['variant_stock'] < (int)$item['quantity']) {
                    $variantLabel = implode(' / ', array_filter([$item['color'], $item['size']]));
                    return $this->jsonResponse(400, false,
                        "Insufficient stock for '{$item['product_name']}' ({$variantLabel}). "
                        . "Only {$item['variant_stock']} left."
                    );
                }
            } else {
                if ((int)$item['product_stock'] < (int)$item['quantity']) {
                    return $this->jsonResponse(400, false,
                        "Insufficient stock for '{$item['product_name']}'. Only {$item['product_stock']} left."
                    );
                }
            }

            $subtotal += (float)$item['line_total'];
        }

        $subtotal    = round($subtotal, 2);
        $totalAmount = round($subtotal + $shippingFee, 2);

        // ----------------------------------------
        // Generate unique reference
        // ----------------------------------------
        $reference = 'ORD-' . strtoupper(bin2hex(random_bytes(8))) . '-' . time();
        $order_number = 'ORD-' . strtoupper(bin2hex(random_bytes(8)));

        // ----------------------------------------
        // Initialize Paystack transaction
        // ----------------------------------------
        $paystackSecret = $this->config->item('paystack_secret_key');

        $paystackPayload = [
            'email'     => $user->email,
            'amount'    => (int)round($totalAmount * 100), // kobo
            'currency'  => 'NGN',
            'reference' => $reference,
            'metadata'  => [
                'user_id'       => $user->user_id,
                'cart_id'       => $cart['id'],
                'delivery_type' => $deliveryType,
                'shipping_fee'  => $shippingFee,
            ]
        ];

        $paystackResponse = $this->paystackPost('/transaction/initialize', $paystackPayload, $paystackSecret);

        if (!$paystackResponse || !($paystackResponse['status'] ?? false)) {
            $errMsg = $paystackResponse['message'] ?? 'Unable to initiate payment. Please try again.';
            return $this->jsonResponse(502, false, $errMsg);
        }

        $accessCode = $paystackResponse['data']['access_code'];

        // ----------------------------------------
        // Create PENDING order
        // ----------------------------------------
        $this->db->trans_begin();

        $this->db->insert('orders', [
            'user_id'              => $user->user_id,
            'cart_id'              => $cart['id'],
            'order_number'         => $order_number,
            'paystack_reference'   => $reference,
            'paystack_access_code' => $accessCode,
            'subtotal'             => $subtotal,
            'shipping_fee'         => $shippingFee,
            'amount'               => $totalAmount,
            'delivery_type'        => $deliveryType,
            'status'               => 'pending',
            // Address fields — NULL for self_pickup
            'first_name'           => $firstName,
            'last_name'            => $lastName,
            'phone'                => $phone,
            'additional_phone'     => $additionalPhone,
            'address'              => $address,
            'landmark'             => $landmark,
            'state'                => $state,
            'area'                 => $area,
        ]);

        if (!$this->db->affected_rows()) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to create order. Please try again.');
        }

        $orderId = $this->db->insert_id();

        // ----------------------------------------
        // Snapshot cart items → order_items
        // ----------------------------------------
        foreach ($cartItems as $item) {
            $this->db->insert('order_items', [
                'order_id'     => $orderId,
                'product_id'   => $item['product_id'],
                'variant_id'   => $item['variant_id'],
                'product_name' => $item['product_name'],
                'color'        => $item['color'],
                'size'         => $item['size'],
                'unit_price'   => $item['unit_price'],
                'quantity'     => $item['quantity'],
                'line_total'   => $item['line_total'],
            ]);
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return $this->jsonResponse(500, false, 'Unable to create order. Please try again.');
        }

        $this->db->trans_commit();

        return $this->jsonResponse(200, true, 'Checkout initiated.', [
            'reference'    => $reference,
            'access_code'  => $accessCode,
            'subtotal'     => number_format($subtotal, 2, '.', ''),
            'shipping_fee' => number_format($shippingFee, 2, '.', ''),
            'amount'       => number_format($totalAmount, 2, '.', ''),
            'order_id'     => $orderId,
        ]);
    }

    /**
     * Verify payment after Paystack inline popup fires its callback.
     * Frontend sends the reference it got from initiate_checkout.
     * We verify with Paystack's API, then finalize the order.
     * IDEMPOTENT: safe to call more than once for the same reference.
     *
     * Expects: application/json { "reference": "ORD-..." }
     */
    public function verify_payment()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $body      = json_decode(file_get_contents('php://input'), true);
        $reference = trim($body['reference'] ?? '');

        if (empty($reference)) {
            return $this->jsonResponse(400, false, 'Payment reference is required.');
        }

        $order = $this->db
            ->where('paystack_reference', $reference)
            ->get('orders')
            ->row_array();

        if (!$order) {
            return $this->jsonResponse(404, false, 'Order not found.');
        }

        if ((int)$order['user_id'] !== (int)$user->user_id) {
            return $this->jsonResponse(403, false, 'Unauthorized.');
        }

        // Already processed — idempotent return
        if ($order['status'] === 'paid') {
            return $this->jsonResponse(200, true, 'Payment already verified.', [
                'order_id'     => (int)$order['id'],
                'status'       => 'paid',
                'amount'       => number_format((float)$order['amount'], 2, '.', ''),
                'shipping_fee' => number_format((float)$order['shipping_fee'], 2, '.', ''),
            ]);
        }

        if ($order['status'] === 'failed') {
            return $this->jsonResponse(400, false, 'This payment was unsuccessful. Please try again.');
        }

        // ----------------------------------------
        // Verify with Paystack
        // ----------------------------------------
        $paystackSecret   = $this->config->item('paystack_secret_key');
        $paystackResponse = $this->paystackGet('/transaction/verify/' . rawurlencode($reference), $paystackSecret);

        if (!$paystackResponse || !($paystackResponse['status'] ?? false)) {
            return $this->jsonResponse(502, false, 'Unable to verify payment with Paystack. Please try again.');
        }

        $txData = $paystackResponse['data'];

        // Paystack returns kobo — convert back to Naira
        $paidAmountNaira = round($txData['amount'] / 100, 2);
        $expectedNaira   = round((float)$order['amount'], 2);

        if ($txData['status'] !== 'success') {
            $this->db->where('paystack_reference', $reference)->update('orders', [
                'status'          => 'failed',
                'payment_payload' => json_encode($txData),
            ]);
            return $this->jsonResponse(400, false, 'Payment was not successful. Please try again.');
        }

        if ($paidAmountNaira !== $expectedNaira) {
            // Amount mismatch — log and flag
            log_message('error',
                "Amount mismatch for reference {$reference}. "
                . "Expected: {$expectedNaira}, Paid: {$paidAmountNaira}."
            );
            $this->db->where('paystack_reference', $reference)->update('orders', [
                'status'          => 'failed',
                'payment_payload' => json_encode($txData),
            ]);
            return $this->jsonResponse(400, false, 'Payment amount mismatch. Please contact support with reference: ' . $reference);
        }

        return $this->finalizeOrder($order, $txData);
    }

    /**
     * Paystack Webhook — server-to-server safety net.
     *
     * Paystack calls this directly when charge.success fires.
     * Handles cases where the user paid but closed the browser
     * before verify_payment was called.
     *    
     */
    public function paystack_webhook()
    {
        $paystackSecret = $this->config->item('paystack_secret_key');
        $rawBody        = file_get_contents('php://input');
        $signature      = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        $expectedSig    = hash_hmac('sha512', $rawBody, $paystackSecret);

        if (!hash_equals($expectedSig, $signature)) {
            http_response_code(401);
            exit('Invalid signature');
        }

        $event = json_decode($rawBody, true);

        if (($event['event'] ?? '') !== 'charge.success') {
            http_response_code(200);
            exit('OK');
        }

        $txData    = $event['data'];
        $reference = $txData['reference'] ?? '';

        if (empty($reference)) {
            http_response_code(200);
            exit('OK');
        }

        $order = $this->db
            ->where('paystack_reference', $reference)
            ->get('orders')
            ->row_array();

        // Only process pending orders — skip already-paid or missing
        if (!$order || $order['status'] !== 'pending') {
            http_response_code(200);
            exit('OK');
        }

        $paidAmountNaira = round($txData['amount'] / 100, 2);
        $expectedNaira   = round((float)$order['amount'], 2);

        if ($txData['status'] === 'success' && $paidAmountNaira === $expectedNaira) {
            $this->finalizeOrder($order, $txData);
        } else {
            $this->db->where('paystack_reference', $reference)->update('orders', [
                'status'          => 'failed',
                'payment_payload' => json_encode($txData),
            ]);
        }

        http_response_code(200);
        exit('OK');
    }

    /**
     * Fetch the authenticated user's order history, newest first.
     */
    public function fetch_orders()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $orders = $this->db
            ->where('user_id', $user->user_id)
            ->where_not_in('status', ['pending', 'cancelled', 'failed'])
            // ->where('status !=', 'pending')
            // ->where('status !=', 'cancelled')
            // ->where('status !=', 'failed')
            ->order_by('id', 'DESC')
            ->get('orders')
            ->result_array();

        foreach ($orders as &$order) {
            unset($order['paystack_access_code'], $order['payment_payload'], $order['cart_id']);

            $order['subtotal']     = number_format((float)$order['subtotal'], 2, '.', '');
            $order['shipping_fee'] = number_format((float)$order['shipping_fee'], 2, '.', '');
            $order['amount']       = number_format((float)$order['amount'], 2, '.', '');

            // Display-friendly order status
            switch ($order['status']) {
                case 'paid':
                    $order['status'] = 'Processing';
                    break;
                case 'shipped':
                    if ($order['delivery_type'] === 'door_delivery') {
                        $order['status'] = 'Out for Delivery';
                    } elseif ($order['delivery_type'] === 'self_pickup') {
                        $order['status'] = 'Ready for Pickup';
                    }
                    break;
                case 'delivered':
                    $order['status'] = 'Completed';
                    break;
            }
            $order['items'] = $this->db
                ->select('id, product_id, variant_id, product_name, color, size, unit_price, quantity, line_total')
                ->where('order_id', $order['id'])
                ->get('order_items')
                ->result_array();

            foreach ($order['items'] as &$item) {
                $item['unit_price'] = number_format((float)$item['unit_price'], 2, '.', '');
                $item['line_total'] = number_format((float)$item['line_total'], 2, '.', '');
                $item['image_url']  = null;

                if ($item['product_id']) {
                    $image = $this->db
                        ->select('image_path')
                        ->where('product_id', $item['product_id'])
                        ->where('is_spotlight', 1)
                        ->get('product_images')
                        ->row_array();

                    if ($image) {
                        $item['image_url'] = base_url($image['image_path']);
                    }
                }
            }
            unset($item);
        }
        unset($order);

        return $this->jsonResponse(200, true, 'Orders retrieved successfully.', [
            'data' => $orders
        ]);
    }

    /**
     * View single order details
    */
    public function view_order_details()
    {
        $user = $this->authorize();
        if (!$user) {
            return;
        }

        $object = json_decode(file_get_contents("php://input"), true);
        $order_number = isset($object['order_number']) ? $object['order_number'] : 0;

        if ($order_number <= 0) {
            return $this->jsonResponse(
                400,
                false,
                'Invalid order.'
            );
        }

        $order = $this->db
            ->where('order_number', $order_number)
            ->where('user_id', $user->user_id)
            ->where_not_in('status', ['pending', 'cancelled', 'failed'])
            ->get('orders')
            ->row_array();

        if (!$order) {
            return $this->jsonResponse(
                404,
                false,
                'Order not found.'
            );
        }

        unset(
            $order['paystack_access_code'],
            $order['payment_payload'],
            $order['cart_id']
        );

        $order['subtotal']     = number_format((float)$order['subtotal'], 2, '.', '');
        $order['shipping_fee'] = number_format((float)$order['shipping_fee'], 2, '.', '');
        $order['amount']       = number_format((float)$order['amount'], 2, '.', '');

        /*
        |--------------------------------------------------------------------------
        | Display-friendly Status
        |--------------------------------------------------------------------------
        */
        switch ($order['status']) {
            case 'paid':
                $order['status'] = 'New Order';
                break;
            case 'shipped':
                if ($order['delivery_type'] === 'door_delivery') {
                    $order['status'] = 'Out for Delivery';
                } else {
                    $order['status'] = 'Ready for Pickup';
                }
                break;
            case 'delivered':
                $order['status'] = 'Completed';
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | Order Items
        |--------------------------------------------------------------------------
        */
        $order['items'] = $this->db
            ->select('id, product_id, variant_id, product_name, color, size, unit_price, quantity, line_total')
            ->where('order_id', $order['id'])
            ->get('order_items')
            ->result_array();

        foreach ($order['items'] as &$item) {

            $item['unit_price'] = number_format((float)$item['unit_price'], 2, '.', '');
            $item['line_total'] = number_format((float)$item['line_total'], 2, '.', '');

            $item['image_url'] = null;

            if (!empty($item['variant_id'])) {
                // Variant image takes priority
                $variant = $this->db
                    ->select('image_id')
                    ->where('id', $item['variant_id'])
                    ->get('product_variants')
                    ->row_array();

                if (!empty($variant['image_id'])) {
                    $image = $this->db
                        ->select('image_path')
                        ->where('id', $variant['image_id'])
                        ->get('product_images')
                        ->row_array();

                    if ($image) {
                        $item['image_url'] = base_url($image['image_path']);
                    }
                }
            }

            // Fallback to spotlight image
            if (!$item['image_url'] && !empty($item['product_id'])) {

                $image = $this->db
                    ->select('image_path')
                    ->where('product_id', $item['product_id'])
                    ->where('is_spotlight', 1)
                    ->get('product_images')
                    ->row_array();

                if ($image) {
                    $item['image_url'] = base_url($image['image_path']);
                }
            }
        }

        unset($item);

        return $this->jsonResponse(
            200,
            true,
            'Order details retrieved successfully.',
            [
                'data' => $order
            ]
        );
    }

    /**
     * Fetch the all orders not pending for the admin to treat, new orders first. (Admin only)
    */
    public function order_management()
    {
        $user = $this->authorizeStoreAdmins();
        if (!$user) {
            return;
        }

        $orders = $this->db
            ->select("
                orders.*,
                users.first_name AS customer_first_name,
                users.last_name AS customer_last_name,
                users.email AS customer_email,
                users.phone AS customer_phone
            ")
            ->from('orders')
            ->join('users', 'users.id = orders.user_id', 'left')
            ->where_not_in('orders.status', ['pending', 'cancelled', 'failed'])
            // ->where('orders.status !=', 'pending')
            // ->where('orders.status !=', 'cancelled')
            // ->where('orders.status !=', 'failed')
            ->order_by("
                CASE
                    WHEN orders.status = 'paid' THEN 1
                    WHEN orders.status = 'shipped' THEN 2
                    WHEN orders.status = 'delivered' THEN 3
                    WHEN orders.status = 'cancelled' THEN 4
                    ELSE 5
                END
            ", '', false)
            ->order_by('orders.id', 'DESC')
            ->get()
            ->result_array();


        foreach ($orders as &$order) {
            unset($order['paystack_access_code'], $order['payment_payload'], $order['cart_id']);

            $order['subtotal']     = number_format((float)$order['subtotal'], 2, '.', '');
            $order['shipping_fee'] = number_format((float)$order['shipping_fee'], 2, '.', '');
            $order['amount']       = number_format((float)$order['amount'], 2, '.', '');

            // Display-friendly order status
            switch ($order['status']) {
                case 'paid':
                    $order['status'] = 'New Order';
                    break;
                case 'shipped':
                    if ($order['delivery_type'] === 'door_delivery') {
                        $order['status'] = 'Out for Delivery';
                    } elseif ($order['delivery_type'] === 'self_pickup') {
                        $order['status'] = 'Ready for Pickup';
                    }
                    break;
                case 'delivered':
                    $order['status'] = 'Completed';
                    break;
            }
            $order['items'] = $this->db
                ->select('id, product_id, variant_id, product_name, color, size, unit_price, quantity, line_total')
                ->where('order_id', $order['id'])
                ->get('order_items')
                ->result_array();

            foreach ($order['items'] as &$item) {
                $item['unit_price'] = number_format((float)$item['unit_price'], 2, '.', '');
                $item['line_total'] = number_format((float)$item['line_total'], 2, '.', '');
                $item['image_url']  = null;

                if ($item['product_id']) {
                    $image = $this->db
                        ->select('image_path')
                        ->where('product_id', $item['product_id'])
                        ->where('is_spotlight', 1)
                        ->get('product_images')
                        ->row_array();

                    if ($image) {
                        $item['image_url'] = base_url($image['image_path']);
                    }
                }
            }
            unset($item);
        }
        unset($order);

        return $this->jsonResponse(200, true, 'Orders retrieved successfully.', [
            'data' => $orders
        ]);
    }

    public function manage_order_details()
    {
        $user = $this->authorizeStoreAdmins();
        if (!$user) {
            return;
        }

        $object = json_decode(file_get_contents("php://input"), true);
        $order_number = isset($object['order_number']) ? $object['order_number'] : 0;

        if ($order_number <= 0) {
            return $this->jsonResponse(
                400,
                false,
                'Invalid order.'
            );
        }

        $order = $this->db
            ->where('order_number', $order_number)
            ->where_not_in('status', ['pending', 'cancelled', 'failed'])
            ->get('orders')
            ->row_array();

        if (!$order) {
            return $this->jsonResponse(
                404,
                false,
                'Order not found.'
            );
        }

        unset(
            $order['paystack_access_code'],
            $order['payment_payload'],
            $order['cart_id']
        );

        $order['subtotal']     = number_format((float)$order['subtotal'], 2, '.', '');
        $order['shipping_fee'] = number_format((float)$order['shipping_fee'], 2, '.', '');
        $order['amount']       = number_format((float)$order['amount'], 2, '.', '');

        /*
        |--------------------------------------------------------------------------
        | Display-friendly Status
        |--------------------------------------------------------------------------
        */
        switch ($order['status']) {
            case 'paid':
                $order['status'] = 'New Order';
                break;
            case 'shipped':
                if ($order['delivery_type'] === 'door_delivery') {
                    $order['status'] = 'Out for Delivery';
                } else {
                    $order['status'] = 'Ready for Pickup';
                }
                break;
            case 'delivered':
                $order['status'] = 'Completed';
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | Order Items
        |--------------------------------------------------------------------------
        */
        $order['items'] = $this->db
            ->select('id, product_id, variant_id, product_name, color, size, unit_price, quantity, line_total')
            ->where('order_id', $order['id'])
            ->get('order_items')
            ->result_array();

        foreach ($order['items'] as &$item) {

            $item['unit_price'] = number_format((float)$item['unit_price'], 2, '.', '');
            $item['line_total'] = number_format((float)$item['line_total'], 2, '.', '');

            $item['image_url'] = null;

            if (!empty($item['variant_id'])) {
                // Variant image takes priority
                $variant = $this->db
                    ->select('image_id')
                    ->where('id', $item['variant_id'])
                    ->get('product_variants')
                    ->row_array();

                if (!empty($variant['image_id'])) {
                    $image = $this->db
                        ->select('image_path')
                        ->where('id', $variant['image_id'])
                        ->get('product_images')
                        ->row_array();

                    if ($image) {
                        $item['image_url'] = base_url($image['image_path']);
                    }
                }
            }

            // Fallback to spotlight image
            if (!$item['image_url'] && !empty($item['product_id'])) {

                $image = $this->db
                    ->select('image_path')
                    ->where('product_id', $item['product_id'])
                    ->where('is_spotlight', 1)
                    ->get('product_images')
                    ->row_array();

                if ($image) {
                    $item['image_url'] = base_url($image['image_path']);
                }
            }
        }

        unset($item);

        return $this->jsonResponse(
            200,
            true,
            'Order details retrieved successfully.',
            [
                'data' => $order
            ]
        );
    }

    /**
     * Update order status (Admin only)
    */
    public function update_order_status()
    {
        $user = $this->authorizeStoreAdmins();
        if (!$user) {
            return;
        }

        $object = json_decode(file_get_contents("php://input"), true);
        $orderId = isset($object['order_id']) ? (int)$object['order_id'] : 0;
        $status  = isset($object['status']) ? strtolower(trim($object['status'])) : '';
        // Optional note
        $note = isset($object['note']) ? trim($object['note']) : '';
        // Rider details
        $riderDetails = isset($object['rider_details']) ? trim($object['rider_details']) : '';

        if ($orderId <= 0) {
            return $this->jsonResponse(400, false, 'Invalid order.');
        }

        $allowedStatuses = ['shipped', 'delivered'];

        if (!in_array($status, $allowedStatuses)) {
            return $this->jsonResponse(400, false, 'Invalid status.');
        }

        // Fetch order
        $order = $this->db
            ->where('id', $orderId)
            ->get('orders')
            ->row_array();

        if (!$order) {
            return $this->jsonResponse(404, false, 'Order not found.');
        }

        /*
        |--------------------------------------------------------------------------
        | Guards
        |--------------------------------------------------------------------------
        */

        // Payment not completed
        if ($order['status'] === 'pending') {
            return $this->jsonResponse(
                400,
                false,
                'This order has not been paid for.'
            );
        }

        // Cancelled order
        if ($order['status'] === 'cancelled') {
            return $this->jsonResponse(
                400,
                false,
                'Cancelled orders cannot be updated.'
            );
        }

        // Already completed
        if ($order['status'] === 'delivered') {
            return $this->jsonResponse(
                400,
                false,
                'This order has already been completed.'
            );
        }

        // Already at requested status
        if ($order['status'] === $status) {
            return $this->jsonResponse(
                400,
                false,
                'Order is already marked as ' . $status . '.'
            );
        }

        // Cannot skip from paid directly to delivered
        if ($order['status'] === 'paid' && $status === 'delivered') {
            return $this->jsonResponse(
                400,
                false,
                'Order must be shipped before it can be completed.'
            );
        }

        // Cannot move backwards
        if ($order['status'] === 'shipped' && $status === 'paid') {
            return $this->jsonResponse(
                400,
                false,
                'Cannot move order back to paid.'
            );
        }

         /*
        |--------------------------------------------------------------------------
        | Rider Details Guard
        |--------------------------------------------------------------------------
        |
        | Rider details are compulsory ONLY when:
        |
        | paid -> shipped
        |
        | AND it is a door delivery.
        |
        */

        if (
            $order['status'] === 'paid' && $status === 'shipped' && $order['delivery_type'] === 'door_delivery'
        ) {
            if (empty($riderDetails)) {
                return $this->jsonResponse(
                    400,
                    false,
                    'Rider details are required for door delivery.'
                );
            }
        }        

        /*
        |--------------------------------------------------------------------------
        | Display Status
        |--------------------------------------------------------------------------
        */

        $displayStatus = '';

        if ($status === 'shipped') {
            if ($order['delivery_type'] === 'door_delivery') {
                $displayStatus = 'Out for Delivery';
            } elseif ($order['delivery_type'] === 'self_pickup') {
                $displayStatus = 'Ready for Pickup';
            } else {
                return $this->jsonResponse(
                    400,
                    false,
                    'Invalid delivery type for this order.'
                );
            }

        } elseif ($status === 'delivered') {
            $displayStatus = 'Completed';
        }

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        $updateData = [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Save shipped note only when provided
        if ($status === 'shipped' && $note !== '') {
            $updateData['shipped_note'] = $note;
        }

        // Save delivered note only when provided
        if ($status === 'delivered' && $note !== '') {
            $updateData['delivered_note'] = $note;
        }

        // Save rider details only for door delivery
        if (
            $status === 'shipped' &&
            $order['delivery_type'] === 'door_delivery' &&
            $riderDetails !== ''
        ) {
            $updateData['rider_details'] = $riderDetails;
        }        

        $updated = $this->db
            ->where('id', $orderId)
            ->update('orders', $updateData);

        if (!$updated) {
            return $this->jsonResponse(
                500,
                false,
                'Unable to update order.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send Email
        |--------------------------------------------------------------------------
        */

        $emailStatus = '';
        if ($status === 'shipped') {
            if ($order['delivery_type'] === 'door_delivery') {
                $emailStatus = 'out_for_delivery';
            } elseif ($order['delivery_type'] === 'self_pickup') {
                $emailStatus = 'pickup';
            }
        } elseif ($status === 'delivered') {
            $emailStatus = 'completed';
        }

        /*
        |--------------------------------------------------------------------------
        | Get Customer Details
        |--------------------------------------------------------------------------
        */
        $customer = $this->db
            ->select('first_name, last_name, email')
            ->where('id', $order['user_id'])
            ->get('users')
            ->row_array();

        /*
        |--------------------------------------------------------------------------
        | Send Customer Email
        |--------------------------------------------------------------------------
        */

        if ($customer && !empty($customer['email'])) {

            $fullname = trim(
                $customer['first_name'] . ' ' . $customer['last_name']
            );
            $this->sendOrderStatusEmail(
                $customer['email'],
                $fullname,
                $order['order_number'],
                $emailStatus,
                $order['delivery_type'],
                $note,
                $riderDetails
            );
        }

        return $this->jsonResponse(
            200,
            true,
            'Order status updated successfully.',
            [
                'order_id'       => $orderId,
                'order_number'   => $order['order_number'],
                'status'         => $status,
                'display_status' => $displayStatus,
                'note'           => $note,
                'rider_details'  => $status === 'shipped' &&
                                    $order['delivery_type'] === 'door_delivery'
                                    ? $riderDetails
                                    : $order['rider_details']
            ]
        );
    }

    /**
     * Finalizes a paid order in one atomic transaction:
     * - Marks order as paid, saves payment payload
     * - Deducts stock from products/variants
     * - Marks cart as checked_out
     *
     * IDEMPOTENT: caller must confirm status === 'pending' first.
     * Called by both verify_payment() and paystack_webhook().
     */
    private function finalizeOrder(array $order, array $txData)
    {
        $orderId   = (int)$order['id'];
        $reference = $order['paystack_reference'];

        $this->db->trans_begin();

        // Mark order paid
        $this->db->where('paystack_reference', $reference)->update('orders', [
            'status'          => 'paid',
            'paid_at'         => date('Y-m-d H:i:s'),
            'payment_payload' => json_encode($txData),
        ]);

        // Deduct stock
        $orderItems = $this->db
            ->where('order_id', $orderId)
            ->get('order_items')
            ->result_array();

        foreach ($orderItems as $item) {
            if ($item['variant_id']) {                
                $this->db->query(
                    'UPDATE product_variants
                    SET quantity = GREATEST(0, quantity - ?)
                    WHERE id = ?',
                    [(int)$item['quantity'], (int)$item['variant_id']]
                );
            } elseif ($item['product_id']) {
                // Deduct from products (no-variant product)
                $this->db->query(
                    'UPDATE products
                    SET quantity = GREATEST(0, quantity - ?)
                    WHERE id = ?',
                    [(int)$item['quantity'], (int)$item['product_id']]
                );
            }
        }

        // Mark cart checked_out so it disappears from the active cart
        if ($order['cart_id']) {
            $this->db->where('id', $order['cart_id'])->update('carts', [
                'status' => 'checked_out'
            ]);
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            log_message('error',
                "FINALIZE FAILED for reference {$reference}. "
                . "Payment confirmed by Paystack but DB update failed. Manual review required."
            );
            // For webhook: this returns a response object but we exit(OK) after anyway
            return $this->jsonResponse(500, false,
                'Payment received but order could not be finalized. '
                . 'Please contact support with reference: ' . $reference
            );
        }

        $this->db->trans_commit();

        return $this->jsonResponse(200, true, 'Payment verified. Order placed successfully.', [
            'order_id'     => $orderId,
            'reference'    => $reference,
            'subtotal'     => number_format((float)$order['subtotal'], 2, '.', ''),
            'shipping_fee' => number_format((float)$order['shipping_fee'], 2, '.', ''),
            'amount'       => number_format((float)$order['amount'], 2, '.', ''),
            'status'       => 'paid',
            'delivery_type'=> $order['delivery_type'],
        ]);
    }

    /**
     * POST to a Paystack endpoint.
     */
    private function paystackPost(string $endpoint, array $payload, string $secret): ?array
    {
        $ch = curl_init('https://api.paystack.co' . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/json',
                'Cache-Control: no-cache',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', 'Paystack POST cURL error: ' . $error);
            return null;
        }
        return json_decode($response, true);
    }

    /**
     * GET from a Paystack endpoint.
     */
    private function paystackGet(string $endpoint, string $secret): ?array
    {
        $ch = curl_init('https://api.paystack.co' . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $secret,
                'Cache-Control: no-cache',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', 'Paystack GET cURL error: ' . $error);
            return null;
        }
        return json_decode($response, true);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function cleanupImages(array $uploadedImages): void
    {
        foreach ($uploadedImages as $img) {
            $path = FCPATH . $img['image_path'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }   

    private function jsonResponse(int $statusCode, bool $status, string $message, array $extra = [])
    {
        return $this->output
            ->set_status_header($statusCode)
            ->set_content_type('application/json')
            ->set_output(json_encode(array_merge([
                'status'  => $status,
                'message' => $message
            ], $extra)));
    }

}