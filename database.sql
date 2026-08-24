-- ============================================================================
-- EcoAsset Database Schema (Supabase PostgreSQL)
-- Enhanced with Site Ranger Profiles, Crew Coordinates, Required Skills & SLA Fields
-- ============================================================================

-- 1. Drop existing tables and types if they exist (Clean Slate)
DROP TABLE IF EXISTS system_audit_logs CASCADE;
DROP TABLE IF EXISTS maintenance_tasks CASCADE;
DROP TABLE IF EXISTS report_upvotes CASCADE;
DROP TABLE IF EXISTS damage_reports CASCADE;
DROP TABLE IF EXISTS site_ranger_profiles CASCADE;
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
    'SITE_RANGER', 
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

-- 5.2 Site Ranger Profiles
CREATE TABLE site_ranger_profiles (
    user_id UUID PRIMARY KEY REFERENCES users(user_id) ON DELETE CASCADE,
    station_region VARCHAR(100) NOT NULL DEFAULT 'Kuala Lumpur',
    employee_id VARCHAR(50) NOT NULL
);

-- 5.3 Maintenance Crews
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
    assigned_crew_id UUID REFERENCES maintenance_crews(crew_id) ON DELETE SET NULL,
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
