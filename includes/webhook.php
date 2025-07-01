<?php
/**
 * SumSub Webhook Handler
 * 
 * Handles webhook notifications from SumSub verification service
 * URL to configure in SumSub console: https://yourdomain.com/wp-content/plugins/4wp-int-sumsub/includes/webhook.php
 */

declare(strict_types=1);

// WordPress environment check
if (!defined('ABSPATH')) {
    // Load WordPress if accessed directly
    require_once('../../../wp-load.php');
}

// Load SumSub autoloader for additional API calls
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
} else {
    error_log('SumSub Webhook: Autoload not found');
}

/**
 * SumSub Webhook Handler Class
 */
class SumsubWebhookHandler {
    
    private string $secret;
    private string $log_dir;
    private ?\App\Sumsub $sumsub_api;
    
    public function __construct() {
        // Get webhook secret from WordPress options
        $this->secret = get_option('_4wp_int_sumsub_webhook_secret', '');
        $this->log_dir = wp_upload_dir()['basedir'] . '/sumsub-logs/';
        
        // Initialize SumSub API client if needed
        $this->init_sumsub_api();
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
        
        $this->handle_webhook();
    }
    
    /**
     * Initialize SumSub API client
     */
    private function init_sumsub_api(): void {
        try {
            $app_token = get_option('_4wp_int_sumsub_app_token', '');
            $secret_key = get_option('_4wp_int_sumsub_secret_key', '');
            
            if (!empty($app_token) && !empty($secret_key) && class_exists('\App\Sumsub')) {
                $this->sumsub_api = new \App\Sumsub($app_token, $secret_key);
            } else {
                $this->sumsub_api = null;
                error_log('SumSub Webhook: API client not initialized - missing credentials or class');
            }
        } catch (Exception $e) {
            $this->sumsub_api = null;
            error_log('SumSub Webhook: Failed to initialize API client - ' . $e->getMessage());
        }
    }
    
    /**
     * Main webhook handling logic
     */
    private function handle_webhook(): void {
        try {
            // Get raw payload
            $raw_payload = file_get_contents('php://input');
            $headers = $this->get_request_headers();
            
            // Log incoming request
            $this->log_request($headers, $raw_payload);
            
            // Verify webhook signature
            if (!$this->verify_signature($raw_payload, $headers)) {
                $this->send_error(403, 'Invalid signature');
                return;
            }
            
            // Parse and process payload
            $data = json_decode($raw_payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->send_error(400, 'Invalid JSON payload');
                return;
            }
            
            // Process webhook data
            $this->process_webhook_data($data);
            
            // Send success response
            $this->send_success();
            
        } catch (Exception $e) {
            error_log('SumSub Webhook Error: ' . $e->getMessage());
            $this->send_error(500, 'Internal server error');
        }
    }
    
    /**
     * Get request headers (cross-platform)
     */
    private function get_request_headers(): array {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        
        // Fallback for servers without getallheaders()
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header_name = str_replace('_', '-', substr($key, 5));
                $headers[$header_name] = $value;
            }
        }
        return $headers;
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_signature(string $payload, array $headers): bool {
        if (empty($this->secret)) {
            error_log('SumSub Webhook: Secret not configured');
            return false;
        }
        
        $received_signature = $headers['X-PAYLOAD-DIGEST'] ?? '';
        $algorithm = strtoupper($headers['X-PAYLOAD-DIGEST-ALG'] ?? '');
        
        // Check algorithm
        if ($algorithm !== 'HMAC_SHA256_HEX') {
            error_log("SumSub Webhook: Unsupported algorithm: {$algorithm}");
            return false;
        }
        
        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $payload, $this->secret);
        
        return hash_equals($expected_signature, $received_signature);
    }
    
    /**
     * Process webhook data based on event type
     */
    private function process_webhook_data(array $data): void {
        $event_type = $data['type'] ?? '';
        $review_status = $data['reviewStatus'] ?? '';
        $applicant_id = $data['applicantId'] ?? '';
        $external_user_id = $data['externalUserId'] ?? '';
        
        // Log processed webhook
        $this->log_processed_webhook($data);
        
        switch ($event_type) {
            case 'applicantReviewed':
                $this->handle_applicant_reviewed($data);
                break;
                
            case 'applicantCreated':
                $this->handle_applicant_created($data);
                break;
                
            case 'applicantDeleted':
                $this->handle_applicant_deleted($data);
                break;
                
            default:
                error_log("SumSub Webhook: Unknown event type: {$event_type}");
        }
        
        // WordPress action hook for custom processing
        do_action('sumsub_webhook_received', $data, $event_type, $review_status);
    }
    
    /**
     * Handle applicant reviewed event
     */
    private function handle_applicant_reviewed(array $data): void {
        $review_status = $data['reviewStatus'] ?? '';
        $applicant_id = $data['applicantId'] ?? '';
        $external_user_id = $data['externalUserId'] ?? '';
        
        // Extract WordPress user ID from external user ID
        $wp_user_id = $this->extract_wp_user_id($external_user_id);
        
        switch ($review_status) {
            case 'completed':
                $this->handle_verification_completed($wp_user_id, $applicant_id, $data);
                break;
                
            case 'rejected':
                $this->handle_verification_rejected($wp_user_id, $applicant_id, $data);
                break;
                
            case 'pending':
                $this->handle_verification_pending($wp_user_id, $applicant_id, $data);
                break;
        }
    }
    
    /**
     * Handle verification completed
     */
    private function handle_verification_completed(int $wp_user_id, string $applicant_id, array $data): void {
        if ($wp_user_id > 0) {
            // Update user meta
            update_user_meta($wp_user_id, '_sumsub_verification_status', 'completed');
            update_user_meta($wp_user_id, '_sumsub_applicant_id', $applicant_id);
            update_user_meta($wp_user_id, '_sumsub_verification_date', current_time('mysql'));
            
            // Get additional verification details via API
            if ($this->sumsub_api) {
                try {
                    $applicant_details = $this->sumsub_api->getApplicantStatus($applicant_id);
                    update_user_meta($wp_user_id, '_sumsub_verification_details', json_encode($applicant_details));
                } catch (Exception $e) {
                    error_log("SumSub Webhook: Failed to get applicant details for {$applicant_id}: " . $e->getMessage());
                }
            }
            
            // WordPress action for completed verification
            do_action('sumsub_verification_completed', $wp_user_id, $applicant_id, $data);
        }
        
        error_log("SumSub: Verification completed for user {$wp_user_id}, applicant {$applicant_id}");
    }
    
    /**
     * Handle verification rejected
     */
    private function handle_verification_rejected(int $wp_user_id, string $applicant_id, array $data): void {
        if ($wp_user_id > 0) {
            update_user_meta($wp_user_id, '_sumsub_verification_status', 'rejected');
            update_user_meta($wp_user_id, '_sumsub_rejection_reason', $data['rejectLabels'] ?? '');
            
            do_action('sumsub_verification_rejected', $wp_user_id, $applicant_id, $data);
        }
        
        error_log("SumSub: Verification rejected for user {$wp_user_id}, applicant {$applicant_id}");
    }
    
    /**
     * Handle verification pending
     */
    private function handle_verification_pending(int $wp_user_id, string $applicant_id, array $data): void {
        if ($wp_user_id > 0) {
            update_user_meta($wp_user_id, '_sumsub_verification_status', 'pending');
            
            do_action('sumsub_verification_pending', $wp_user_id, $applicant_id, $data);
        }
    }
    
    /**
     * Handle applicant created event
     */
    private function handle_applicant_created(array $data): void {
        error_log('SumSub: New applicant created: ' . ($data['applicantId'] ?? 'unknown'));
        do_action('sumsub_applicant_created', $data);
    }
    
    /**
     * Handle applicant deleted event
     */
    private function handle_applicant_deleted(array $data): void {
        error_log('SumSub: Applicant deleted: ' . ($data['applicantId'] ?? 'unknown'));
        do_action('sumsub_applicant_deleted', $data);
    }
    
    /**
     * Extract WordPress user ID from external user ID
     */
    private function extract_wp_user_id(string $external_user_id): int {
        // Pattern: wp_user_123_1672531200
        if (preg_match('/^wp_user_(\d+)_\d+$/', $external_user_id, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }
    
    /**
     * Log incoming request
     */
    private function log_request(array $headers, string $payload): void {
        $log_entry = "==== " . date('Y-m-d H:i:s') . " ====\n";
        $log_entry .= "HEADERS:\n";
        foreach ($headers as $key => $value) {
            $log_entry .= "{$key}: {$value}\n";
        }
        $log_entry .= "\nPAYLOAD:\n{$payload}\n\n";
        
        file_put_contents($this->log_dir . 'webhook_requests.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log processed webhook
     */
    private function log_processed_webhook(array $data): void {
        $log_entry = "==== " . date('Y-m-d H:i:s') . " ====\n";
        $log_entry .= "PROCESSED WEBHOOK:\n";
        $log_entry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        file_put_contents($this->log_dir . 'webhook_processed.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Send error response
     */
    private function send_error(int $code, string $message): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
    
    /**
     * Send success response
     */
    private function send_success(): void {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Initialize webhook handler
new SumsubWebhookHandler();