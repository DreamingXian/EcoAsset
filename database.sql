-- ============================================================================
-- EcoAsset Database Schema & Seed Data (Supabase PostgreSQL)
-- Enhanced with Maintenance Crew Coordinates, Required Skills & SLA Fields
-- ============================================================================

-- 1. Drop existing tables and types if they exist (Clean Slate)
DROP TABLE IF EXISTS system_audit_logs CASCADE;
DROP TABLE IF EXISTS maintenance_tasks CASCADE;
DROP TABLE IF EXISTS report_upvotes CASCADE;
DROP TABLE IF EXISTS damage_reports CASCADE;
DROP TABLE IF EXISTS maintenance_crews CASCADE;
DROP TABLE IF EXISTS tourist_profiles CASCADE;
DROP TABLE IF EXISTS users CASCADE;

DROP TYPE IF EXISTS report_status CASCADE;
DROP TYPE IF EXISTS issue_category CASCADE;
DROP TYPE IF EXISTS availability_state CASCADE;
DROP TYPE IF EXISTS skill_category CASCADE;
DROP TYPE IF EXISTS user_role CASCADE;

-- 2. Enable UUID Extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 3. Create Custom ENUM Types
CREATE TYPE user_role AS ENUM (
    'TOURIST', 
    'MAINTENANCE_CREW', 
    'ADMINISTRATOR'
);

CREATE TYPE skill_category AS ENUM (
    'ELECTRICAL', 
    'STRUCTURAL', 
    'SANITATION',
    'GENERAL_FACILITY',
    'OTHER'
);

CREATE TYPE availability_state AS ENUM (
    'AVAILABLE', 
    'BUSY'
);

CREATE TYPE issue_category AS ENUM (
    'CRITICAL_SAFETY', 
    'MAJOR_BLOCKAGE',
    'MINOR_DEFECT',
    'AESTHETIC',
    'OPERATIONAL'
);

CREATE TYPE report_status AS ENUM (
    'OPEN_FOR_CLAIM',
    'PENDING_ACCEPTANCE',
    'IN_PROGRESS',
    'PRECOMPLETE',
    'DROPPED',
    'PRE_INVALID',
    'COMPLETED',
    'INVALID_RESOLVED'
);

-- ============================================================================
-- 4. Base Users Table
-- ============================================================================
CREATE TABLE users (
    user_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role user_role NOT NULL DEFAULT 'TOURIST',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 5. Sub-Profile Tables (Specialized Actors)
-- ============================================================================

-- 5.1 Tourist Profiles
CREATE TABLE tourist_profiles (
    user_id UUID PRIMARY KEY REFERENCES users(user_id) ON DELETE CASCADE,
    reputation_score INT NOT NULL DEFAULT 100
);

-- 5.2 Maintenance Crews
CREATE TABLE maintenance_crews (
    crew_id UUID PRIMARY KEY REFERENCES users(user_id) ON DELETE CASCADE,
    skill_tags skill_category[] NOT NULL DEFAULT '{}',
    availability_status availability_state NOT NULL DEFAULT 'AVAILABLE',
    performance_score NUMERIC(5, 2) NOT NULL DEFAULT 100.00,
    current_latitude DECIMAL(10, 8) DEFAULT 3.139003,
    current_longitude DECIMAL(11, 8) DEFAULT 101.686855,
    station_region VARCHAR(100) DEFAULT 'Kuala Lumpur'
);

-- ============================================================================
-- 6. Damage Reports Table
-- ============================================================================
CREATE TABLE damage_reports (
    report_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES users(user_id) ON DELETE SET NULL,
    category issue_category NOT NULL,
    required_skill skill_category NOT NULL DEFAULT 'STRUCTURAL',
    description TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    photo_url TEXT NOT NULL,
    exif_timestamp TIMESTAMP WITH TIME ZONE NOT NULL,
    upvote_count INT NOT NULL DEFAULT 0,
    priority_score NUMERIC(6, 2) NOT NULL DEFAULT 0.00,
    status report_status NOT NULL DEFAULT 'OPEN_FOR_CLAIM',
    district VARCHAR(100) DEFAULT 'Kuala Lumpur',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 7. Report Upvotes Table (Prevents Duplicate Upvotes)
-- ============================================================================
CREATE TABLE report_upvotes (
    upvote_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    report_id UUID NOT NULL REFERENCES damage_reports(report_id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_user_report_upvote UNIQUE (report_id, user_id)
);

-- ============================================================================
-- 8. Maintenance Tasks Table (Work Orders & Execution Lifecycle)
-- ============================================================================
CREATE TABLE maintenance_tasks (
    task_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    report_id UUID UNIQUE NOT NULL REFERENCES damage_reports(report_id) ON DELETE CASCADE,
    assigned_crew_id UUID REFERENCES users(user_id) ON DELETE SET NULL,
    assigned_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    sla_expires_at TIMESTAMP WITH TIME ZONE,
    completion_photo_url TEXT,
    resolution_notes TEXT,
    decline_reason TEXT,
    drop_reason TEXT,
    flag_invalid_photo_url TEXT,
    flag_invalid_notes TEXT,
    admin_return_reason TEXT,
    completed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 9. System Audit Logs Table (Immutable Audit Trail)
-- ============================================================================
CREATE TABLE system_audit_logs (
    log_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    actor_id UUID REFERENCES users(user_id) ON DELETE SET NULL,
    action_type VARCHAR(100) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================================
-- 10. Performance & Geospatial Indexes
-- ============================================================================
CREATE INDEX idx_reports_pscore ON damage_reports (priority_score DESC);
CREATE INDEX idx_reports_status ON damage_reports (status);
CREATE INDEX idx_reports_location ON damage_reports (latitude, longitude);
CREATE INDEX idx_reports_district ON damage_reports (district);
CREATE INDEX idx_crew_availability ON maintenance_crews (availability_status);
CREATE INDEX idx_tasks_crew ON maintenance_tasks (assigned_crew_id);
CREATE INDEX idx_tasks_sla ON maintenance_tasks (sla_expires_at);
CREATE INDEX idx_audit_logs_timestamp ON system_audit_logs (timestamp DESC);

-- ============================================================================
-- 11. Seed Pre-Configured Demo Accounts (All Passwords: Pass1234!)
-- Hash: $2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu
-- ============================================================================

-- 11.1 Administrators
INSERT INTO users (user_id, email, password_hash, full_name, role, is_active) VALUES
('a0000000-0000-0000-0000-000000000001', 'admin@ecoasset.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'Zarina Aziz (Municipal Director)', 'ADMINISTRATOR', TRUE),
('a0000000-0000-0000-0000-000000000002', 'admin@dbkl.gov.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'DBKL Chief Administrator', 'ADMINISTRATOR', TRUE);

-- 11.2 Maintenance Crews
INSERT INTO users (user_id, email, password_hash, full_name, role, is_active) VALUES
('c0000000-0000-0000-0000-000000000001', 'crew@ecoasset.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'Tan Boon Huat (Structural Specialist)', 'MAINTENANCE_CREW', TRUE),
('c0000000-0000-0000-0000-000000000002', 'boonhuat@contractor.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'Boon Huat Team', 'MAINTENANCE_CREW', TRUE),
('c0000000-0000-0000-0000-000000000003', 'selvam@contractor.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'Selvam Raman (Electrical Lead)', 'MAINTENANCE_CREW', TRUE),
('c0000000-0000-0000-0000-000000000004', 'kaiming@contractor.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'Lee Kai Ming (Sanitation Lead)', 'MAINTENANCE_CREW', TRUE);

INSERT INTO maintenance_crews (crew_id, skill_tags, availability_status, performance_score, current_latitude, current_longitude, station_region) VALUES
('c0000000-0000-0000-0000-000000000001', ARRAY['STRUCTURAL', 'GENERAL_FACILITY']::skill_category[], 'AVAILABLE', 96.50, 3.139003, 101.686855, 'Kuala Lumpur'),
('c0000000-0000-0000-0000-000000000002', ARRAY['STRUCTURAL']::skill_category[], 'AVAILABLE', 94.00, 3.141200, 101.691200, 'Bukit Bintang'),
('c0000000-0000-0000-0000-000000000003', ARRAY['ELECTRICAL', 'GENERAL_FACILITY']::skill_category[], 'AVAILABLE', 98.00, 3.157800, 101.711800, 'KLCC'),
('c0000000-0000-0000-0000-000000000004', ARRAY['SANITATION', 'GENERAL_FACILITY']::skill_category[], 'AVAILABLE', 92.00, 3.143200, 101.698700, 'Chinatown');

-- 11.3 Public Tourist / Citizens
INSERT INTO users (user_id, email, password_hash, full_name, role, is_active) VALUES
('f0000000-0000-0000-0000-000000000001', 'tourist@ecoasset.my', '$2y$10$biY5YMrzJjDVow7Q.qoXd.8Ka5jCAHLQQ2aJzOayH9vQs.V2sYKPu', 'Ahmad Faizal (Verified Citizen)', 'TOURIST', TRUE);

INSERT INTO tourist_profiles (user_id, reputation_score) VALUES
('f0000000-0000-0000-0000-000000000001', 100);

-- ============================================================================
-- 12. Seed Demo Active Damage Reports & Maintenance Tasks
-- ============================================================================

-- Report 1: Critical Safety (Pending Acceptance for crew@ecoasset.my)
INSERT INTO damage_reports (report_id, user_id, category, required_skill, description, latitude, longitude, photo_url, exif_timestamp, upvote_count, priority_score, status, district) VALUES
('e0000000-0000-0000-0000-000000000001', 'f0000000-0000-0000-0000-000000000001', 'CRITICAL_SAFETY', 'STRUCTURAL', 'Severe structural crack on main walkway railing near entrance stairs posing immediate visitor hazard.', 3.141500, 101.688000, 'https://images.unsplash.com/photo-1590381105924-c72589b9ef3f?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL '2 hours', 7, 8.80, 'PENDING_ACCEPTANCE', 'Bukit Bintang');

INSERT INTO maintenance_tasks (task_id, report_id, assigned_crew_id, assigned_at, sla_expires_at) VALUES
('d0000000-0000-0000-0000-000000000001', 'e0000000-0000-0000-0000-000000000001', 'c0000000-0000-0000-0000-000000000001', NOW() - INTERVAL '2 hours', NOW() + INTERVAL '22 hours');

-- Report 2: Major Blockage (In Progress for selvam@contractor.my)
INSERT INTO damage_reports (report_id, user_id, category, required_skill, description, latitude, longitude, photo_url, exif_timestamp, upvote_count, priority_score, status, district) VALUES
('e0000000-0000-0000-0000-000000000002', 'f0000000-0000-0000-0000-000000000001', 'MAJOR_BLOCKAGE', 'ELECTRICAL', 'Exposed high-voltage lighting cable and malfunctioning lamp post causing complete darkness on foot path.', 3.158000, 101.712000, 'https://images.unsplash.com/photo-1541888946425-d0fbb18903c7?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL '5 hours', 12, 7.60, 'IN_PROGRESS', 'KLCC');

INSERT INTO maintenance_tasks (task_id, report_id, assigned_crew_id, assigned_at, sla_expires_at) VALUES
('d0000000-0000-0000-0000-000000000002', 'e0000000-0000-0000-0000-000000000002', 'c0000000-0000-0000-0000-000000000003', NOW() - INTERVAL '4 hours', NOW() + INTERVAL '20 hours');

-- Report 3: Minor Defect (Open Pool for Claim)
INSERT INTO damage_reports (report_id, user_id, category, required_skill, description, latitude, longitude, photo_url, exif_timestamp, upvote_count, priority_score, status, district) VALUES
('e0000000-0000-0000-0000-000000000003', 'f0000000-0000-0000-0000-000000000001', 'MINOR_DEFECT', 'SANITATION', 'Public park drainage overflow and clogged trash grate causing foul odor near tourist promenade.', 3.143500, 101.699000, 'https://images.unsplash.com/photo-1530587191325-3db32d826c18?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL '1 day', 4, 5.20, 'OPEN_FOR_CLAIM', 'Chinatown');

INSERT INTO maintenance_tasks (task_id, report_id, assigned_crew_id, assigned_at, sla_expires_at) VALUES
('d0000000-0000-0000-0000-000000000003', 'e0000000-0000-0000-0000-000000000003', NULL, NULL, NULL);

-- Report 4: Aesthetic / Pre-Complete Proof Review (Completed on site by crew, pending admin approval)
INSERT INTO damage_reports (report_id, user_id, category, required_skill, description, latitude, longitude, photo_url, exif_timestamp, upvote_count, priority_score, status, district) VALUES
('e0000000-0000-0000-0000-000000000004', 'f0000000-0000-0000-0000-000000000001', 'AESTHETIC', 'STRUCTURAL', 'Damaged park bench wooden planks repaired and repainted.', 3.149000, 101.693000, 'https://images.unsplash.com/photo-1517646287270-a5a9ca602e5c?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL '2 days', 3, 3.40, 'PRECOMPLETE', 'Bukit Bintang');

INSERT INTO maintenance_tasks (task_id, report_id, assigned_crew_id, assigned_at, sla_expires_at, completion_photo_url, resolution_notes) VALUES
('d0000000-0000-0000-0000-000000000004', 'e0000000-0000-0000-0000-000000000004', 'c0000000-0000-0000-0000-000000000001', NOW() - INTERVAL '2 days', NULL, 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=600&q=80', 'Replaced 4 timber planks and applied anti-termite weather coat.');
