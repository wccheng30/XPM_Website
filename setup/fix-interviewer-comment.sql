-- Fix: applications.interviewer_comment must allow NULL on insert
-- Run once on the production database if needed.
ALTER TABLE applications
  MODIFY interviewer_comment TEXT NULL DEFAULT NULL;
