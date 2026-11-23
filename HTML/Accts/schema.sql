-- EventHub database schema (MySQL 5.7+ / MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS eventhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventhub;

-- Table 1: Users (common table for everyone)
CREATE TABLE IF NOT EXISTS Users (
  UserID           INT AUTO_INCREMENT PRIMARY KEY,
  Username         VARCHAR(50)  NOT NULL UNIQUE,
  Email            VARCHAR(255) NOT NULL UNIQUE,
  PasswordHash     VARCHAR(255) NOT NULL,
  Phone            VARCHAR(20)  NULL,
  City             VARCHAR(100) NULL,
  ProfileImg       VARCHAR(255) NULL,
  NotificationType ENUM('Email','Disabled','Order Updates','Promotional Updates','All Updates') NOT NULL DEFAULT 'Email',
  Role             ENUM('Customer','Organizer','Admin') NOT NULL,
  IsActive         BOOLEAN      NOT NULL DEFAULT TRUE,
  RecordDateTime   DATETIME     NOT NULL,
  CreatedBy        INT          NULL,
  CONSTRAINT fk_users_createdby FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table 2: Customers
CREATE TABLE IF NOT EXISTS Customers (
  CustomerID      INT AUTO_INCREMENT PRIMARY KEY,
  UserID          INT NOT NULL UNIQUE,
  BillingAddress  VARCHAR(255) NULL,
  PostalCode      VARCHAR(20)  NULL,
  TotalPurchases  INT NOT NULL DEFAULT 0,
  RecordDateTime  DATETIME NOT NULL,
  CreatedBy       INT NULL,
  CONSTRAINT fk_customers_user    FOREIGN KEY (UserID)    REFERENCES Users(UserID) ON DELETE CASCADE,
  CONSTRAINT fk_customers_creator FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Table 3: EventOrganizers
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
  CONSTRAINT fk_orgs_user    FOREIGN KEY (UserID)    REFERENCES Users(UserID) ON DELETE CASCADE,
  CONSTRAINT fk_orgs_creator FOREIGN KEY (CreatedBy) REFERENCES Users(UserID) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Extra: supports the Reset Password page (the new password only applies once the emailed link is clicked)
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
