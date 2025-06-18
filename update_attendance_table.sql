-- Add new photo path columns
ALTER TABLE attendance
ADD COLUMN photo_path_front VARCHAR(255) AFTER photo_path,
ADD COLUMN photo_path_left VARCHAR(255) AFTER photo_path_front,
ADD COLUMN photo_path_right VARCHAR(255) AFTER photo_path_left;

-- Migrate existing photo_path data to photo_path_front
UPDATE attendance 
SET photo_path_front = photo_path 
WHERE photo_path IS NOT NULL;

-- Drop the old photo_path column
ALTER TABLE attendance
DROP COLUMN photo_path; 