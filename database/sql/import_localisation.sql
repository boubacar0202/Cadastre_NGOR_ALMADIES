-- Localisation déjà enregistrée dans cadastre_ngor_almadies.
-- Région Dakar, département Dakar, arrondissement Almadies,
-- communes Ngor, Ouakam et Yoff.
-- Ces identifiants sont ceux des terrains et des imports secrétariat.
-- Ne pas réexécuter sur la base actuelle : les lignes existent déjà.

SET NAMES utf8mb4;
START TRANSACTION;

INSERT INTO `regions` (id, slt_region, created_at, updated_at) VALUES
(1, 'Dakar', '2026-11-09 17:52:43', '2026-11-09 17:52:43');

INSERT INTO `departements` (id, slt_departement, region_id, created_at, updated_at) VALUES
(1, 'Dakar', 1, '2026-11-09 17:52:43', '2026-11-09 17:52:43');

INSERT INTO `arrondissements` (id, slt_arrondissement, departement_id, created_at, updated_at) VALUES
(1, 'Almadies', 1, '2026-11-09 17:52:43', '2026-11-09 17:52:43');

INSERT INTO `communes` (id, slt_commune, arrondissement_id, txt_codeCommune, created_at, updated_at) VALUES
(1, 'Ngor', 1, '01130132', '2026-11-09 17:52:58', '2026-11-09 17:52:58'),
(2, 'Ouakam', 1, '01130131', '2026-11-09 17:52:58', '2026-11-09 17:52:58'),
(3, 'Yoff', 1, '01130133', '2026-11-09 17:52:58', '2026-11-09 17:52:58');

COMMIT;
