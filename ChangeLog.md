# ChangeLog — Module Paie Dolibarr

## 1.1.0

- **Espace self-service salarié** : droit dédié « Consulter ses propres
  bulletins », menu « Mes bulletins de paie », consultation + téléchargement
  PDF strictement limités aux bulletins validés du salarié connecté.
- **Téléchargement PDF fiable** : flux direct depuis la fiche (plus de
  dépendance à `document.php`), génération à la volée si absent.
- **Aperçu PDF intégré** en bas de la fiche bulletin, de l'onglet Documents et
  de l'espace salarié (comme les factures).
- **PDF** : entêtes de colonnes courts et centrés, rétrécissement automatique
  des libellés longs (plus de chevauchement de colonnes).
- **Intégration bancaire** : au passage en « Payé », création automatique de
  l'écriture bancaire du net à payer sur le compte paramétré (configuration :
  compte des salaires + option d'activation). L'écriture est supprimée si le
  bulletin repasse en brouillon (refusé si l'écriture est déjà rapprochée).
- Migration automatique du schéma (`fk_bank`) à la réactivation du module,
  sans perte de données.

## 1.0.0

Première version fonctionnelle.

- Descripteur de module Dolibarr (menus, droits, activation, seed automatique).
- Tables : `paie_contrat`, `paie_rubrique`, `paie_bulletin`, `paie_bulletin_ligne`.
- Gestion des salariés (contrats) : matricule, n° SS, emploi, qualification,
  coefficient, ancienneté, type de contrat, salaire de base, temps de travail.
- Catalogue de rubriques paramétrable, pré-rempli avec les cotisations françaises
  regroupées selon le bulletin clarifié (Santé, AT/MP, Retraite, Famille, Chômage,
  CSG/CRDS, Autres, Allègements).
- Génération automatique d'un bulletin depuis le contrat + catalogue.
- Moteur de calcul : brut, cotisations salariales/patronales par tranches
  (plafond SS, tranche 2, assiette CSG 98,25 %), net à payer, net imposable,
  net social, coût employeur, cumuls annuels.
- Édition PDF au format légal français (modèle `paiestandard`).
- Configuration employeur (raison sociale, SIRET, APE/NAF, URSSAF, convention,
  plafond mensuel de la Sécurité sociale).
- Moteur de calcul validé par banc d'essai automatisé.
