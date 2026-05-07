<?php
namespace App\Controllers;

use App\Response;
use App\Middleware\AuthMiddleware;
use OpenApi\Attributes as OA;

class UploadController
{
    #[OA\Post(
        path: '/api/upload',
        operationId: 'uploadImage',
        summary: 'Upload an image (admin only)',
        tags: ['Upload'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['image'],
                    properties: [
                        new OA\Property(property: 'image', type: 'string', format: 'binary', description: 'JPG, PNG, WebP or GIF — max 5 MB'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Image uploaded',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status',  type: 'integer', example: 201),
                        new OA\Property(property: 'message', type: 'string',  example: 'Uploaded'),
                        new OA\Property(property: 'data',    type: 'object',
                            properties: [
                                new OA\Property(property: 'url', type: 'string', example: '/Szalmaprojekt/uploads/abc123.jpg'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Admin role required'),
            new OA\Response(response: 422, description: 'No file, wrong type, or too large'),
        ]
    )]
    public function upload(array $request): void
    {
        AuthMiddleware::handle($request, ['admin']);

        if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            Response::error('No file uploaded', 422);
        }

        $file    = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $maxSize = 5 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('Upload error code ' . $file['error'], 500);
        }
        if ($file['size'] > $maxSize) {
            Response::error('File too large — max 5 MB', 422);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            Response::error('Only JPG, PNG, WebP and GIF images are allowed', 422);
        }

        $ext       = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        };
        $filename  = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../../uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            file_put_contents($uploadDir . '.htaccess',
                "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|cgi)$\">\n    Deny from all\n</FilesMatch>\n"
            );
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            Response::error('Failed to save file', 500);
        }

        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        Response::json(['url' => $base . '/uploads/' . $filename], 201, 'Uploaded');
    }
}
