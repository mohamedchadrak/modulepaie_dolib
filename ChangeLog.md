# ChangeLog — Module Paie Dolibarr

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
