# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Les agents du bureau du cadastre de Ngor-Almadies, au guichet. Leur travail est d’enregistrer un dossier et de le traiter.

## Product Purpose

Outil interne du bureau pour enregistrer et suivre les dossiers cadastraux. Le succès, c’est qu’un agent trouve le terrain, saisisse le dossier et le fasse avancer sans quitter l’application.

## Operating Context

Le bureau traite des dossiers fonciers sur place : secrétariat, courrier arrivé et départ, mutations, lotissements, matrice cadastrale et attribution d’un dossier à un agent. L’application tourne sur le réseau local du bureau.

## Capabilities and Constraints

L’application couvre la saisie d’un dossier (parcelle, NICAD, titulaire, références cadastrales), le courrier, les mutations, les lotissements et la matrice.

Contraintes confirmées :

- L’interface reste en français, avec les termes officiels : NICAD, titre, lotissement, bail.
- L’usage est celui du bureau, sur le réseau local. Ce n’est pas un site public.

Non décidé : rien n’a été confirmé sur l’alignement futur des formulaires au-delà de la saisie actuelle.

## Brand Commitments

Le nom du produit est Cadastre Ngor Almadies.

## Evidence on Hand

Le code de l’application et les imports de dossiers déjà en base (secrétariat 2023 et le fichier 2024 prêt à charger). Il n’y a pas de témoignages, d’études d’usagers ni de preuves externes à inventer.

## Product Principles

- L’écran sert l’agent au guichet qui enregistre ou traite un dossier.
- Les termes cadastraux officiels restent exacts. On ne les remplace pas par un vocabulaire générique.
- L’outil reste une application interne du bureau, sur son réseau local.
- L’identité d’un dossier (NICAD, titre, lot, commune) reste la même d’une étape à l’autre.
