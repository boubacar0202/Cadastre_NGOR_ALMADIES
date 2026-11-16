-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : jeu. 12 nov. 2026 à 13:44
-- Version du serveur : 10.4.28-MariaDB
-- Version de PHP : 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `cadastre_kedougou`
--

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `txt_numeroNote` varchar(255) NOT NULL,
  `txt_typeNote` varchar(255) NOT NULL,
  `txt_titreNote` varchar(255) NOT NULL,
  `fichierPDF` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `services`
--

INSERT INTO `services` (`id`, `txt_numeroNote`, `txt_typeNote`, `txt_titreNote`, `fichierPDF`, `created_at`, `updated_at`) VALUES
(3, '2020-765', 'Décret', 'mode de calcul de la Contribution Foncière des Propriétés Bâties', 'service/uvMbWjVM7hjKZIOTbVTDIhbV1ZX1dHB8Ks6pPLNY.pdf', '2026-01-08 14:35:46', '2026-01-08 14:35:46'),
(4, '2012-1440', 'Décret', 'décret portant classification du réseau routier national', 'service/dt1eLJujgeFESO4BPWNCsQn58uR9gamjm9Jg0hYO.pdf', '2026-01-08 14:38:09', '2026-01-08 14:38:09'),
(5, '150 MEFP/DGID/DCAD du 06 avril 2018', 'Note de service', 'Note de service portant délivrance attestation du cadastre', 'service/89XEY2bGkxVUQLFuhkdJkWff0JdaQXQDV2fsTQgO.pdf', '2026-01-08 14:51:17', '2026-01-08 14:51:17'),
(6, '307 MFB/DGID/DCAD/akw du 31 décembre 2025', 'Note de service', 'Note de Service portant  Harmonisation des pratiques et documents relatifs à la copropriété', 'service/r9jnSa0tU8BazgHehJc83CF0ONA9XYyNuQq2vivD.pdf', '2026-01-08 14:55:00', '2026-01-08 14:55:00'),
(7, '2014-143 du 5 février 2014', 'Décret', 'relatif au montant du loyer des locaux à usage d’habitation', 'service/A5u6EBVAUYRzlOezbGh5kelJCUkzsPnDv1Vmsa6Z.pdf', '2026-01-08 15:09:07', '2026-01-08 15:09:07'),
(8, '2014-144 du 5 février 2014', 'Décret', 'modifiant le décret n° 81-683 du 7 juillet 1981 Fixant les éléments de calcul du loyer des locaux à usage d’habitation', 'service/UFFhkC6IimWZ3qVPVvOAWkYfvflxzvl0v1mKoMEL.pdf', '2026-01-08 15:10:22', '2026-01-08 15:10:22'),
(9, '289 MFB/DGID/DCAD du 21 aout 2019', 'Note de service', 'Modèle de situation foncière', 'service/kLjhhqPJDX1AZ4enhSwiyQ7k8S4igMcdYgNSFtip.pdf', '2026-01-08 15:23:08', '2026-01-08 15:23:08'),
(10, '042092 du 13 décembre 2021', 'Arrété', 'Arrêté portant tarification des services du cadastre.', 'service/YCwFOozRGlxqwGfrSUCoOAnYPiIY8yLY3Th1DKr3.pdf', '2026-01-08 20:13:46', '2026-01-08 20:13:46'),
(11, '954 MEFP/DGID/DCAD du 12 octobre 2017', 'Note de service', 'Transcription du NICAD sur les documents délivrés par les services de la DGID', 'service/kgbv0HyBdZ7srx7BwUz28zvfQgxNmTNs99gJufla.pdf', '2026-01-08 20:49:13', '2026-01-08 20:49:13'),
(12, '995 MFB/DGID du 31 mai 2024', 'Note de service', 'note de service portant codification des identifiants des livres fonciers.', 'service/LqlsgUpdadU4IvRiNIdA5POEQwgLf5dY3kUsmlwY.pdf', '2026-01-08 20:59:20', '2026-01-08 20:59:20'),
(13, '625 MFB/DGID/DCAD/ADCAD/asg-bs', 'Note de service', 'PRESERVATION DES EMPRISE DU RESEAU ROUTIER', 'service/0Y5SxU8cqlVmQoakt6InKF2aUa5H0pKXZxBzJ3sX.pdf', '2026-01-08 22:06:25', '2026-01-08 22:06:25'),
(14, '032034 du 20 octobre 2025', 'Arrété', 'UTILISATION DU NICAD COMME NUMERO D\'IDENTIFICATION FISCALE POUR LES CONTRIBUABLES NE DISPOSANT PAS DE NINEA', 'service/Czf8zixC7FypTF0h5aPfs0nO2JXaKViwaRLqT13I.pdf', '2026-01-09 10:10:08', '2026-01-09 10:10:08'),
(15, '2010-439 du 06 avril 2010', 'Décret', 'Barème des prix des terrains nus et des terrains bâtis, applicables en matière de loyer', 'service/aUpFsloCPJ6hgxF1iTTgTzyuW13efmDYQ7KUBygQ.pdf', '2026-01-13 23:09:06', '2026-01-13 23:09:06'),
(16, '76-66 du 02 juillet 1976', 'loi', 'Code du domaine de l\'Etat', 'service/b3SLHy430Cza3HLgFTK9AhRc0aC7hBZux9Wv718h.pdf', '2026-01-14 16:52:02', '2026-01-14 16:52:02'),
(17, '2011-07 du 30 mars 2011', 'loi', 'Régime de la Propriété Foncière', 'service/h987zNMiVRB1btBAX5dBSfRMmmuwXk8ZAewisAQK.pdf', '2026-01-14 16:58:22', '2026-01-14 16:58:22'),
(18, '2012-396 du 27 mars 2012', 'Décret', 'Instituant le NICAD', 'service/dSoCo1z8ttQ09ISEwYJnRVpdH68VxSSI3WgDmnIn.pdf', '2026-01-14 17:06:28', '2026-01-14 17:06:28'),
(19, '64-46 du 17 juin 1964', 'loi', 'Relatif au Domaine national', 'service/yNgBkB9k4Ue06hSHmLjeBMQRWczo3H6AM5Jy43iM.pdf', '2026-01-14 23:14:45', '2026-01-14 23:14:45'),
(20, 'Pratique Immatriculation 1', 'Autres', 'Cas pratique de procédure d\'immatriculation d\'un terrain du Domaine national', 'service/GGr2xafoE09SMiLzvDTVL4NSfv7obEhlCFO2Qu14.pdf', '2026-01-14 23:42:44', '2026-01-14 23:42:44'),
(21, '165 MEF/DGID/DCAD du 11 avril 2013', 'Note cadre', 'Modalités d\'application du décret 2012-396 du 27 mars 2012 portant création du NICAD', 'service/76ReNKo2QcKIq3udcO1NtCNgebaWyGKrbYiVWxHt.pdf', '2026-01-14 23:54:00', '2026-01-14 23:54:00'),
(22, '2025-1194 du 17 juillet 2025', 'Décret', 'Partie réglementaire du code de l\'urbanisme', 'service/oLTZAMBrpLw1jMFTcnBbRQwnfDqsF3LEETea2R4p.pdf', '2026-01-29 12:00:00', '2026-01-29 12:00:00'),
(23, '2023-20 du 29 décembre 2023', 'loi', 'Code urbanisme 2023', 'service/fkFYdLw7s7UgLpUSOrPn1Du5jPl1NuvNY6wyTOdx.pdf', '2026-01-29 12:06:43', '2026-01-29 12:06:43'),
(24, '5439 du 03 novembre 2025', 'Circulaire', 'Lettre circulaire relative à l\'approbation des délibérations dans le cadre du PROCASEF', 'service/5m5Jl2tuaCVeHd8GRukbAHBj2xszjjvWxxbkHcUX.pdf', '2026-01-29 13:46:59', '2026-01-29 13:46:59'),
(25, 'MLF/AG/197/10.2026', 'Documents', 'Information relative à l\'ouverture à Kédougou du Cabinet Jambaar Topo de Mor Talla Gueye', 'service/mgGOjdz5eiZavEM2XAsiiEFkd8g9iogywMtceLvY.pdf', '2026-10-14 11:01:05', '2026-10-14 11:01:05');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
