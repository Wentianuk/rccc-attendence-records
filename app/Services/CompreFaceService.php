<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Exception;

class CompreFaceService
{
    private $client;
    private $apiKey;
    private $baseUrl;
    private $recognitionApiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.compreface.base_url', 'http://localhost:8000');
        $this->apiKey = config('services.compreface.api_key', '2ac2d70f-8ae9-4bcb-bc8a-6490ede3cf96');
        $this->recognitionApiKey = $this->apiKey;
        
        if (empty($this->apiKey)) {
            Log::error('CompreFace API key not configured');
            throw new Exception('CompreFace API key not properly configured. Please check your environment settings.');
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => true,
            'verify' => false, // Skip SSL verification for local development
            'headers' => [
                'Accept' => 'application/json',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS, PUT, DELETE',
                'Access-Control-Allow-Headers' => 'DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,x-api-key'
            ]
        ]);
    }

    public function addFace($imageData, $subjectId)
    {
        try {
            if (empty($imageData)) {
                throw new Exception('No image data provided');
            }

            if (empty($subjectId)) {
                throw new Exception('No subject ID provided');
            }

            // Validate image data
            $imageSize = strlen($imageData);
            if ($imageSize < 1024) { // Less than 1KB
                throw new Exception('Image file is too small. Please provide a clearer photo.');
            }

            if ($imageSize > 10 * 1024 * 1024) { // More than 10MB
                throw new Exception('Image file is too large. Maximum size is 10MB.');
            }

            Log::info('Attempting to add face to CompreFace', [
                'subject_id' => $subjectId,
                'image_size' => $imageSize,
                'url' => $this->baseUrl . '/api/v1/recognition/faces'
            ]);

            // Create temporary file for multipart upload
            $tempFile = tempnam(sys_get_temp_dir(), 'face_');
            file_put_contents($tempFile, $imageData);

            try {
                $response = $this->client->post('/api/v1/recognition/faces', [
                    'multipart' => [
                        [
                            'name' => 'file',
                            'contents' => fopen($tempFile, 'r'),
                            'filename' => 'face.jpg'
                        ],
                        [
                            'name' => 'subject',
                            'contents' => $subjectId
                        ],
                        [
                            'name' => 'det_prob_threshold',
                            'contents' => '0.7'
                        ]
                    ],
                    'headers' => [
                        'x-api-key' => $this->apiKey
                    ]
                ]);

                $result = json_decode($response->getBody()->getContents(), true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON response from CompreFace');
                }

                Log::info('Successfully added face to CompreFace', [
                    'subject_id' => $subjectId,
                    'response' => $result
                ]);

                return $result;

            } finally {
                // Clean up temporary file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = 'Error communicating with CompreFace';
            $context = [
                'error' => $e->getMessage(),
                'request' => [
                    'method' => $e->getRequest()->getMethod(),
                    'url' => (string) $e->getRequest()->getUri(),
                    'headers' => $e->getRequest()->getHeaders()
                ]
            ];

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                
                $context['response'] = [
                    'status_code' => $statusCode,
                    'body' => $responseBody
                ];

                switch ($statusCode) {
                    case 401:
                        $errorMessage = 'Invalid API key. Please check your CompreFace configuration.';
                        break;
                    case 403:
                        $errorMessage = 'Access denied. Please check your API permissions.';
                        break;
                    case 404:
                        $errorMessage = 'CompreFace API endpoint not found.';
                        break;
                    case 413:
                        $errorMessage = 'Image file is too large. Maximum size is 10MB.';
                        break;
                    case 415:
                        $errorMessage = 'Unsupported image format. Please use JPEG, PNG, or JPG.';
                        break;
                    default:
                        try {
                            $responseData = json_decode($responseBody, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($responseData['message'])) {
                                $errorMessage = $responseData['message'];
                            }
                        } catch (\Exception $jsonEx) {
                            // Keep the default error message
                        }
                }
            }

            Log::error('CompreFace API error', $context);
            throw new Exception($errorMessage);

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('Could not connect to CompreFace service', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl
            ]);
            throw new Exception('Could not connect to face recognition service. Please check if the service is running.');

        } catch (Exception $e) {
            Log::error('Error adding face to CompreFace', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function recognizeFace($imageData)
    {
        try {
            if (empty($imageData)) {
                throw new Exception('No image data provided');
            }

            Log::info('Attempting to recognize face', [
                'image_size' => strlen($imageData),
                'url' => $this->baseUrl . '/api/v1/recognition/recognize'
            ]);

            $response = $this->client->post('/api/v1/recognition/recognize', [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $imageData,
                        'filename' => 'face.jpg'
                    ],
                    [
                        'name' => 'det_prob_threshold',
                        'contents' => '0.7'
                    ],
                    [
                        'name' => 'limit',
                        'contents' => '1'
                    ],
                    [
                        'name' => 'prediction_count',
                        'contents' => '1'
                    ]
                ],
                'headers' => [
                    'x-api-key' => $this->apiKey
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from CompreFace');
            }

            Log::info('Face recognition completed', [
                'result' => $result
            ]);

            return $result;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = 'Error communicating with CompreFace';
            $context = [
                'error' => $e->getMessage(),
                'request' => [
                    'method' => $e->getRequest()->getMethod(),
                    'url' => (string) $e->getRequest()->getUri(),
                    'headers' => $e->getRequest()->getHeaders()
                ]
            ];

            if ($e->hasResponse()) {
                $statusCode = $e->getResponse()->getStatusCode();
                $responseBody = $e->getResponse()->getBody()->getContents();
                
                $context['response'] = [
                    'status_code' => $statusCode,
                    'body' => $responseBody
                ];

                switch ($statusCode) {
                    case 401:
                        $errorMessage = 'Invalid API key. Please check your CompreFace configuration.';
                        break;
                    case 403:
                        $errorMessage = 'Access denied. Please check your API permissions.';
                        break;
                    case 404:
                        $errorMessage = 'CompreFace API endpoint not found.';
                        break;
                    default:
                        try {
                            $responseData = json_decode($responseBody, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($responseData['message'])) {
                                $errorMessage = $responseData['message'];
                            }
                        } catch (\Exception $jsonEx) {
                            // Keep the default error message
                        }
                }
            }

            Log::error('CompreFace API error during recognition', $context);
            throw new Exception($errorMessage);

        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('Could not connect to CompreFace service during recognition', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl
            ]);
            throw new Exception('Could not connect to face recognition service. Please check if the service is running on port 8081.');

        } catch (Exception $e) {
            Log::error('Error during face recognition', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function deleteFace($faceId)
    {
        try {
            $response = $this->client->delete("/api/v1/recognition/faces/{$faceId}", [
                'headers' => [
                    'x-api-key' => $this->apiKey
                ]
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('Error deleting face from CompreFace: ' . $e->getMessage());
            throw $e;
        }
    }
} 