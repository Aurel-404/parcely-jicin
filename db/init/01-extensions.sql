-- Runs once, when the database volume is created.
-- Replaces the image's default init script, which also installs tiger geocoder and topology (not needed here).
CREATE EXTENSION IF NOT EXISTS postgis;
