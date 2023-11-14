# Change Log
All notable changes to this project will be documented in this file.

## [Unreleased]

- FIX: add product was not working because of access to array with zero elements *26/06/2023*
- FIX: save buttons on nomenclature tab in products not working due to access to wrong nomenclature table fields *23/06/2023*

## 4.9

- FIX : DA024062 - Fatal sur list.php en php8 *13/11/2023*  4.9.18
- FIX : DA023653 - Nomenclature vide sur les lignes libres + on affichait tous les temps les données de la nomenclature parente *23/08/2023* 4.9.17
- FIX : DA023653 - Erreur d'arrondi lors de calculs *26/07/2023* 4.9.16
- FIX : Fix php8 fatal *05/07/2023* - 4.9.15
- FIX : Fix php8 fatal *28/06/2023* - 4.9.14
- FIX : order of inital table creation not correct. TNomenclatureDet checks for table llx_nomenclature_coef which needs to be created first *19/06/2023*  4.9.13
- FIX : DA023411 - On ne voit plus les enfants sur une fiche nomenclature sur un produit *31/05/2023* 4.9.12
- FIX : DA023377 - Si on édite une ligne de nomenclature après l'ajout d'une ligne à un document, on perdait les fk_product des lignes *31/05/2023* 4.9.11
- FIX : DA023328 - Anomalie nomenclature *24/05/2023* 4.9.10
- FIX : DA023085 - Ajout d'un test *30/03/2023* 4.9.9
- FIX : DA023030 - Lors de l'ajout d'un produit sans nomenclature, la modale affiche une nomenclature vide, celle-ci doit rester vide jusqu'à ce qu'une actions soit effectué depuis la modale *16/03/2023* 4.9.8
- FIX : Compatibilité v17 - Warning PHP 8 *26/01/2023* 4.9.7
- FIX : Compatibilité v17 *04/01/2023* 4.9.6
- FIX : DA022742 PDO Error : try to save NAN instead of a number *04/01/2023* 4.9.5
- FIX : Warning:  current() expects parameter 1 to be array, null given *22/09/2022* 4.9.4
- FIX : ADD test array - *21/09/2022)* - 4.9.3
- FIX : Ajout de la class objet_std dans nomenclature.class *21/09/2022* 4.9.2
- FIX : Module icon - *30/09/2022* - 4.9.1
- NEW : Ajout de la class TechATM pour l'affichage de la page "A propos" *11/05/2022* 4.9.0

## 4.8

- FIX : PHP8 - *05/08/2022* - 4.8.3
- FIX : V16 COMPAT - *27/06/2022* - 4.8.2
- FIX : detail calcul error - *21/07/2022* - 4.8.1
- NEW : Ajout de la possibilité de consommer les produits enfants lors de l'appui sur le bouton "Produire X Quantité" - *28/04/2022* - 4.8.0
- FIX : Calcul JS *18/02/2022* - 4.7.1
- NEW : Improve save performance save only necessary lines *15/12/2021* - 4.7.0

## 4.6 
- FIX : Compatibility with quickcustomerprice causes bug due to parsing a formatted value for qty - *12/09/2022* - 4.6.9
- FIX : Devided by zero  - *23/06/2022* - 4.6.8
- FIX : retrocompatibilité des nomenclatures non-locales présente (fatal PHP sur enregistrement en nomenclature locale)  - *12/04/2022* - 4.6.7
- FIX : les lignes en option vidaient leur PU et PA car recalculé sur la base d'une qty à 0  - *12/04/2022* - 4.6.6
- FIX : compatibilité avec quickcustomerprice  - *12/04/2022* - 4.6.5
- FIX : warning division par 0 sur les lignes en option  - *12/04/2022* - 4.6.4
- FIX : problème select2 z-index => le select2 d'ajout de produit affichait sont dropdown derrière la popin... ajout impossible - *12/04/2022* - 4.6.3
- FIX : Ne pas mettre à jour la ligne de facture à l'ajout pour les avoirs (ça n'a pas de sens) - *11/04/2022* - 4.6.2
- FIX : Le filtre PMP sur la liste des nomenclatures ne fonctionnait pas. Le calcul du prix de vente conseillé n'était pas correct - *09/02/2022* - 4.6.1
- NEW : Ajout d'une action de masse permettant de remplacer le pmp des produits des nomenclatures sélectionnées par le prix des nomenclatures - *04/02/2022* - 4.6.0

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
- FIX : Nouvelle quantité en fonction du pourcentage de perte - Adapter l'arrondi en fonction de la conf MAIN_MAX_DECIMALS_UNIT - *8/12/2022* - 4.2.3
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

### 3.0
- FIX : add createfromclone context for product clone creation to clone nomenclature in the same time


