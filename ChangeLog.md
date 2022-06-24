# Change Log
All notable changes to this project will be documented in this file.

## [Unreleased]

## 4.5

- NEW : Rapport chiffre d'affaires par détail nomenclature / ouvrage - *07/01/2022* - 4.5.0

## 4.4

- NEW : Ajout nomenclatures sur les lignes de factures - *24/12/2021* - 4.4.0

## 4.3

- FIX : Suppression de l'option "Désactivé" de la conf "Prix d'achat/revient suggéré par défaut" - *06/01/2022* - 4.3.4
- FIX : Gestion des remises lignes pour la fonction recursive qui tient compte de la part de produits / services disponibles dans les ouvrages et sous ouvrages des lignes - *06/01/2022* - 4.3.3
- FIX : Gestion des avoirs pour la fonction récursive (avec soustraction des marges en cas de ligne négative) - *04/01/2022* - 4.3.2
- FIX : Le rang n'était pas appliqué au moment de l'import des nomenclatures + ajout du titre de la nomenclature à l'import - *05/01/2022* - 4.3.1
- NEW : Fonction récursive (visible dans le module BTP) pour que les marges présentes dans le tableau des cmd, propal, factures
	tiennent compte de la part de produits / services disponibles dans les ouvrages et sous ouvrages des lignes

## 4.2
- FIX : Erreurs Ajax : exceptions manquantes à la protection CSRF sur
        `prod_ajax.php` - *24/06/2022* - 4.2.2
- FIX : Arrondi faussait le prix d'achat unitaire d'une ligne de nomenclature - *17/12/2021* - 4.2.1
- NEW : ajout de la conf "Titre unique pour les nomenclatures". Si activée, Dolibarr interdira
  la création de deux nomenclatures ayant le même nom - *29/11/2021* - 4.2.0
- NEW : choix du séparateur de champs CSV pour l'import de nomenclatures (par
  défaut : `,`) *25/08/2021* - 4.1.1
- NEW/FIX : Refonte de l'interface des retours chantiers *13/07/2021* - 4.1.0

## 4.0

- FIX : NOSCRFCHECK compatibilty v14 *2021-09-15* - 4.0.1
- NEW : Compatibility with Workstation ATM for Dolibarr v14 *28/06/2021* - 4.0.0  
  **requires WorkstationAtm 2.0**
- FIX : missing en_US translations. *2021-04-21* - 3.2.3

### 3.2

- FIX : add createfromclone context for product clone creation to clone nomenclature in the same time *17/12/2021* - 3.2.6
- FIX : Separator parameter nomenclature import => IMPORT NEW DE MAIN SUITE A ERREUR *30/08/2021* - 3.2.6
- FIX : Import nomenclature afficher message d'erreur + ne pas créer de nomenclature quand il y a une erreur de composant *2021-08-27* - 3.2.5
- FIX : import setup - columns order *2021-08-25* - 3.2.4
- FIX : missing en_US translations. *2021-04-21* - 3.2.3
- NEW : Project feedback history. *2020-12-15*
  Ajoute un onglet "Historique affectation chantier" dans projet qui liste les historiques des affectations de chantier groupés par date avec possibilité de changer la granularité de groupe ex : jour, semaine, mois, années
- NEW : Project feedback resume. *2020-12-16*
  Ajoute sur l'onglet vue d’ensemble du projet, une ligne dans la partie Bénéfice concernant les affectations de chantier calculé sur le PMP des mouvements de stock.

