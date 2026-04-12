<?php

namespace Controller\Api;

use Core\ApiController;
use Core\Request;
use Core\Session;
use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class UploadsController extends ApiController
{
    /**
     * Endpoints:
     * - GET /api/uploads
     * - POST /api/uploads/menu_image
     */
    public function index(): void
    {
        $this->ok([
            'endpoints' => [
                'POST /api/uploads/menu_image',
            ],
        ]);
    }

    public function menu_image(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        {
            $this->methodNotAllowed(['POST']);
            return;
        }

        $request = new Request();
        $payload = $request->post();

        if (!$this->verifyCsrfToken($payload) || !$this->requireStaffUser())
        {
            return;
        }

        $file = $request->files('image');
        if (!is_array($file) || empty($file))
        {
            $this->validationError(['image' => 'Image file is required']);
            return;
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK)
        {
            $this->validationError(['image' => 'Image upload failed']);
            return;
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath))
        {
            $this->validationError(['image' => 'Invalid uploaded file']);
            return;
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > (2 * 1024 * 1024))
        {
            $this->validationError(['image' => 'Image must be 2MB or less']);
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
        if ($finfo)
        {
            finfo_close($finfo);
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime]))
        {
            $this->validationError(['image' => 'Only jpg, png, or webp images are allowed']);
            return;
        }

        $extension = $allowed[$mime];
        $uploadDir = ROOTPATH . 'uploads/menu';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir))
        {
            $this->error('Unable to save uploaded image', 500);
            return;
        }

        if (is_dir($uploadDir) && !is_writable($uploadDir))
        {
            @chmod($uploadDir, 0777);
        }

        if (!is_writable($uploadDir))
        {
            $this->error('Upload directory is not writable', 500);
            return;
        }

        $filename = 'menu-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $target))
        {
            $this->error('Unable to save uploaded image', 500);
            return;
        }

        $this->ok([
            'image_path' => '/uploads/menu/' . $filename,
        ], 201);
    }

    private function requireStaffUser(): mixed
    {
        $session = new Session();
        $user = $session->user();

        if (!$user)
        {
            $this->unauthenticated();
            return null;
        }

        $role = (string)($user->role ?? User::ROLE_CUSTOMER);
        if (!in_array($role, [User::ROLE_STAFF, User::ROLE_MANAGER], true))
        {
            $this->forbidden('Staff access required');
            return null;
        }

        return $user;
    }

    private function verifyCsrfToken(array $payload = []): bool
    {
        $session = new Session();
        $expected = (string)$session->get('csrf_token', '');

        if ($expected === '')
        {
            $this->error('Invalid CSRF token', 419);
            return false;
        }

        $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($provided === '')
        {
            $provided = (string)($payload['csrfToken'] ?? '');
        }

        if ($provided === '' || !hash_equals($expected, $provided))
        {
            $this->error('Invalid CSRF token', 419);
            return false;
        }

        return true;
    }
}
