-- ==============================================================
-- FILE: schema.sql
-- EventHub database schema (MySQL 5.7+ / MariaDB 10.3+)
-- Run once to create the database and all tables.
-- Search tags used below: SECTION:, TABLE:, COLUMNS:, FK:
-- ==============================================================

-- ==============================================================
-- SECTION: DATABASE SETUP
-- Create the database (utf8mb4 so emoji / accents work) and select it
-- ==============================================================
CREATE DATABASE IF NOT EXISTS eventhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventhub;

-- ==============================================================
-- TABLE: Users
-- Common table for everyone (customers, organizers, admins).
-- Role-specific details live in Customers / EventOrganizers.
-- ==============================================================
CREATE TABLE IF NOT EXISTS Users (
  -- --- COLUMNS: identity ---
  UserID           INT AUTO_INCREMENT PRIMARY KEY,
  Username         VARCHAR(50)  NOT NULL UNIQUE,
  Email            VARCHAR(255) NOT NULL UNIQUE,
  -- --- COLUMNS: credentials (never store plain passwords - this is a password_hash() value) ---
  PasswordHash     VARCHAR(255) NOT NULL,
  -- --- COLUMNS: optional profile details (editable on the profile page) ---
  Phone            VARCHAR(20)  NULL,
  City             VARCHAR(100) NULL,
  ProfileImg       VARCHAR(255) NULL,
  -- --- COLUMNS: preferences, role and status ---
  NotificationType ENUM('Email','Disabled','Order Updates','Promotional Updates','All Updates') NOT NULL DEFAULT 'Email',
  Role             ENUM('Customer','Organizer','Admin') NOT NULL,
  IsActive         BOOLEAN      NOT NULL DEFAULT TRUE,
  -- --- COLUMNS: audit trail (when the row was created / by whom) ---
  RecordDateTime   DATETIME     NOT NULL,
  CreatedBy        INT          NULL,
  -- --- FK: CreatedBy points back at Users (self-reference); set to NULL if that user is deleted ---
  CONSTRAINT fk_users_createdby FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================
-- TABLE: Customers
-- One row per Customer user: billing details and purchase count
-- ==============================================================
CREATE TABLE IF NOT EXISTS Customers (
  CustomerID      INT AUTO_INCREMENT PRIMARY KEY,
  UserID          INT NOT NULL UNIQUE,
  BillingAddress  VARCHAR(255) NULL,
  PostalCode      VARCHAR(20)  NULL,
  TotalPurchases  INT NOT NULL DEFAULT 0,
  RecordDateTime  DATETIME NOT NULL,
  CreatedBy       INT NULL,
  -- --- FK: deleting the user deletes their Customers row (CASCADE) ---
  CONSTRAINT fk_customers_user    FOREIGN KEY (UserID)    REFERENCES Users(UserID) ON DELETE CASCADE,
  CONSTRAINT fk_customers_creator FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================
-- TABLE: EventOrganizers
-- One row per Organizer user: business details and verification state
-- ==============================================================
CREATE TABLE IF NOT EXISTS EventOrganizers (
  OrganizerID        INT AUTO_INCREMENT PRIMARY KEY,
  UserID             INT NOT NULL UNIQUE,
  OrganizationName   VARCHAR(150) NULL,
  BusinessEmail      VARCHAR(255) NULL,
  BusinessPhone      VARCHAR(20)  NULL,
  Description        TEXT NULL,
  VerificationStatus ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  RecordDateTime     DATETIME NOT NULL,
  CreatedBy          INT NULL,
  -- --- FK: deleting the user deletes their EventOrganizers row (CASCADE) ---
  CONSTRAINT fk_orgs_user    FOREIGN KEY (UserID)    REFERENCES Users(UserID) ON DELETE CASCADE,
  CONSTRAINT fk_orgs_creator FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==============================================================
-- TABLE: PasswordResets
-- Supports the Reset Password page.
-- The new password only applies once the emailed link is clicked (token is stored hashed).
-- ==============================================================
CREATE TABLE IF NOT EXISTS PasswordResets (
  ResetID         INT AUTO_INCREMENT PRIMARY KEY,
  UserID          INT NOT NULL,
  TokenHash       CHAR(64) NOT NULL UNIQUE,
  NewPasswordHash VARCHAR(255) NOT NULL,
  ExpiresAt       DATETIME NOT NULL,
  UsedAt          DATETIME NULL,
  RecordDateTime  DATETIME NOT NULL,
  CONSTRAINT fk_resets_user FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
) ENGINE=InnoDB;
