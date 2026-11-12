<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function tables(): array
    {
        $ts = 'created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL';
        return [
            'users' => "CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(60) NOT NULL, name_bn VARCHAR(150) NOT NULL, name_en VARCHAR(150) NULL,
                email VARCHAR(150) NULL, mobile VARCHAR(20) NULL, nid VARCHAR(20) NULL, password VARCHAR(255) NOT NULL, role VARCHAR(30) NOT NULL, designation VARCHAR(150) NULL,
                office_name VARCHAR(200) NULL, division_id BIGINT UNSIGNED NULL, district_id BIGINT UNSIGNED NULL, upazila_id BIGINT UNSIGNED NULL, union_name VARCHAR(100) NULL,
                requiring_body_id BIGINT UNSIGNED NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, last_login_at TIMESTAMP NULL, $ts,
                UNIQUE KEY uq_users_username (username), KEY idx_users_role (role, district_id, upazila_id), KEY idx_users_mobile (mobile)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'api_tokens' => "CREATE TABLE api_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) NOT NULL, expires_at TIMESTAMP NULL,
                last_used_at TIMESTAMP NULL, $ts, UNIQUE KEY uq_token (token_hash), KEY idx_tok_user (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'divisions' => "CREATE TABLE divisions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(10) NOT NULL, name_bn VARCHAR(100) NOT NULL, name_en VARCHAR(100) NULL, $ts, UNIQUE KEY uq_div (code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'districts' => "CREATE TABLE districts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, division_id BIGINT UNSIGNED NOT NULL, code VARCHAR(10) NOT NULL, short_code VARCHAR(6) NOT NULL,
                name_bn VARCHAR(100) NOT NULL, name_en VARCHAR(100) NULL, $ts, UNIQUE KEY uq_dist (code), KEY idx_dist_div (division_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'upazilas' => "CREATE TABLE upazilas (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, district_id BIGINT UNSIGNED NOT NULL, code VARCHAR(10) NOT NULL, name_bn VARCHAR(100) NOT NULL,
                name_en VARCHAR(100) NULL, $ts, UNIQUE KEY uq_upa (code), KEY idx_upa_dist (district_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'mouzas' => "CREATE TABLE mouzas (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, upazila_id BIGINT UNSIGNED NOT NULL, name_bn VARCHAR(100) NOT NULL, jl_no VARCHAR(20) NULL, $ts,
                KEY idx_mouza_upa (upazila_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'land_classes' => "CREATE TABLE land_classes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL, name_bn VARCHAR(100) NOT NULL, $ts, UNIQUE KEY uq_lc (code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'requiring_bodies' => "CREATE TABLE requiring_bodies (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name_bn VARCHAR(200) NOT NULL, name_en VARCHAR(200) NULL,
                body_type VARCHAR(20) NOT NULL DEFAULT 'govt', ministry VARCHAR(200) NULL, address VARCHAR(300) NULL, phone VARCHAR(30) NULL, email VARCHAR(150) NULL, $ts) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'applications' => "CREATE TABLE applications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tracking_no VARCHAR(40) NULL, app_code VARCHAR(60) NULL, la_case_no VARCHAR(40) NULL,
                requiring_body_id BIGINT UNSIGNED NOT NULL, created_by BIGINT UNSIGNED NOT NULL, body_type VARCHAR(20) NOT NULL DEFAULT 'govt', app_type VARCHAR(20) NOT NULL DEFAULT 'acquisition',
                project_name_bn VARCHAR(400) NOT NULL, project_name_en VARCHAR(400) NULL, pd_name_bn VARCHAR(150) NULL, pd_name_en VARCHAR(150) NULL, pd_designation VARCHAR(150) NULL,
                pd_office_address VARCHAR(400) NULL, pd_mobile VARCHAR(20) NULL, pd_email VARCHAR(150) NULL, pd_nid VARCHAR(20) NULL, division_id BIGINT UNSIGNED NULL, district_id BIGINT UNSIGNED NULL,
                subject TEXT NULL, has_prior_acquisition TINYINT(1) NOT NULL DEFAULT 0, prior_project_name VARCHAR(400) NULL, prior_la_case_no VARCHAR(60) NULL, is_npp TINYINT(1) NOT NULL DEFAULT 0,
                flags LONGTEXT NULL, status VARCHAR(30) NOT NULL DEFAULT 'draft', phase VARCHAR(120) NULL, total_area_acre DECIMAL(14,4) NOT NULL DEFAULT 0, authority_level VARCHAR(20) NULL,
                submitted_at TIMESTAMP NULL, objection_due_at TIMESTAMP NULL, claims_open TINYINT(1) NOT NULL DEFAULT 0, compensation_open TINYINT(1) NOT NULL DEFAULT 0,
                deposit_required DECIMAL(18,2) NOT NULL DEFAULT 0, compensation_total DECIMAL(18,2) NOT NULL DEFAULT 0, refine_note TEXT NULL, version INT NOT NULL DEFAULT 1, $ts,
                UNIQUE KEY uq_app_track (tracking_no), KEY idx_app_status (status), KEY idx_app_district (district_id), KEY idx_app_body (requiring_body_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'application_lands' => "CREATE TABLE application_lands (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL, upazila_id BIGINT UNSIGNED NOT NULL,
                mouza_id BIGINT UNSIGNED NULL, survey_name VARCHAR(30) NULL, jl_no VARCHAR(20) NULL, khatian_no VARCHAR(30) NOT NULL, dag_no VARCHAR(30) NOT NULL, record_class_id BIGINT UNSIGNED NULL,
                usage_class_id BIGINT UNSIGNED NULL, total_area_acre DECIMAL(12,4) NOT NULL DEFAULT 0, proposed_area_acre DECIMAL(12,4) NOT NULL DEFAULT 0, cha_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                note VARCHAR(400) NULL, $ts, KEY idx_land_app (application_id), KEY idx_land_upa (upazila_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'land_owners' => "CREATE TABLE land_owners (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, land_id BIGINT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, nid VARCHAR(20) NULL, mobile VARCHAR(20) NULL,
                email VARCHAR(150) NULL, address VARCHAR(400) NULL, share DECIMAL(10,6) NULL, mutation_done TINYINT(1) NOT NULL DEFAULT 1, extra LONGTEXT NULL, $ts, KEY idx_owner_land (land_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'attachments' => "CREATE TABLE attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT 'proposal', slot_no INT NULL,
                title VARCHAR(300) NULL, path VARCHAR(400) NOT NULL, original_name VARCHAR(255) NOT NULL, mime VARCHAR(100) NULL, size BIGINT UNSIGNED NOT NULL DEFAULT 0, expires_on DATE NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'pending', note VARCHAR(400) NULL, ref_id BIGINT UNSIGNED NULL, uploaded_by BIGINT UNSIGNED NOT NULL, $ts, KEY idx_att_app (application_id, kind, slot_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'tasks' => "CREATE TABLE tasks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL, step_code VARCHAR(12) NOT NULL, role VARCHAR(30) NOT NULL,
                user_id BIGINT UNSIGNED NULL, district_id BIGINT UNSIGNED NULL, upazila_id BIGINT UNSIGNED NULL, branch_groups VARCHAR(255) NOT NULL DEFAULT '', parent_task_id BIGINT UNSIGNED NULL,
                record_id BIGINT UNSIGNED NULL, status VARCHAR(10) NOT NULL DEFAULT 'pending', due_at TIMESTAMP NULL, completed_at TIMESTAMP NULL, completed_by BIGINT UNSIGNED NULL,
                action VARCHAR(30) NULL, data LONGTEXT NULL, $ts, KEY idx_task_app (application_id, status), KEY idx_task_pick (status, role, district_id, upazila_id), KEY idx_task_user (user_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'task_logs' => "CREATE TABLE task_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL, task_id BIGINT UNSIGNED NULL, step_code VARCHAR(12) NULL,
                action VARCHAR(40) NOT NULL, actor_id BIGINT UNSIGNED NULL, actor_role VARCHAR(30) NULL, comment TEXT NULL, data LONGTEXT NULL, created_at TIMESTAMP NULL, KEY idx_log_app (application_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'records' => "CREATE TABLE records (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(40) NOT NULL, ref_no VARCHAR(60) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'saved', data LONGTEXT NULL, created_by BIGINT UNSIGNED NULL, approved_by BIGINT UNSIGNED NULL, published_at TIMESTAMP NULL, $ts,
                KEY idx_rec_app (application_id, kind), KEY idx_rec_pub (kind, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'payments' => "CREATE TABLE payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, application_id BIGINT UNSIGNED NOT NULL, kind VARCHAR(20) NOT NULL, record_id BIGINT UNSIGNED NULL,
                payee_name VARCHAR(150) NULL, amount DECIMAL(18,2) NOT NULL, method VARCHAR(30) NULL, reference VARCHAR(100) NULL, status VARCHAR(15) NOT NULL DEFAULT 'paid', paid_at TIMESTAMP NULL,
                created_by BIGINT UNSIGNED NULL, $ts, KEY idx_pay_app (application_id, kind)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'audit_logs' => "CREATE TABLE audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, action VARCHAR(60) NOT NULL, entity VARCHAR(40) NULL, entity_id BIGINT UNSIGNED NULL,
                data LONGTEXT NULL, ip VARCHAR(45) NULL, created_at TIMESTAMP NULL, KEY idx_audit_user (user_id), KEY idx_audit_ent (entity, entity_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'number_sequences' => "CREATE TABLE number_sequences (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, scope VARCHAR(80) NOT NULL, year VARCHAR(10) NOT NULL, last_value BIGINT UNSIGNED NOT NULL DEFAULT 0, $ts,
                UNIQUE KEY uq_seq (scope, year)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'holidays' => "CREATE TABLE holidays (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, holiday_date DATE NOT NULL, name VARCHAR(150) NULL, $ts, UNIQUE KEY uq_hol (holiday_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    public function up(): void
    {
        foreach ($this->tables() as $sql) DB::statement(preg_replace('/\s+/', ' ', $sql));
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys($this->tables())) as $t) DB::statement("DROP TABLE IF EXISTS `$t`");
    }
};
