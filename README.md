# Module Paie — Fiches de paie françaises pour Dolibarr

Module Dolibarr **100 % fonctionnel** permettant de gérer et d'éditer des
**bulletins de paie conformes au format légal français** (bulletin de paie
« clarifié » en vigueur depuis 2018, avec net social depuis 2023).

## Fonctionnalités

- Gestion des **salariés** (contrats) : matricule, n° de sécurité sociale,
  emploi, qualification, coefficient, ancienneté, type de contrat (CDI/CDD),
  salaire de base et temps de travail.
- **Catalogue de rubriques** de paie paramétrable (gains, cotisations salariales
  et patronales) avec taux et modes de calcul de base.
- Rubriques de cotisations **pré-remplies** selon le regroupement légal :
  Santé, Accidents du travail, Retraite, Famille, Assurance chômage, CSG/CRDS,
  Autres contributions.
- Génération automatique d'un **bulletin de paie** à partir du contrat du
  salarié et du catalogue de rubriques.
- Calcul automatique du **brut**, des **cotisations salariales / patronales**,
  du **net à payer**, du **net imposable** et du **net social**.
- Édition **PDF** au format légal français (modèle `paiestandard`).
- Gestion des **cumuls annuels**.
- Paramétrage de l'**employeur** (raison sociale, SIRET, code APE/NAF, URSSAF,
  convention collective, plafond de la Sécurité sociale).

## Compatibilité

- Dolibarr **16.0** ou supérieur.
- PHP 7.4+ / 8.x.

## Installation

1. Copier le dossier `modulepaie/` dans `htdocs/custom/` de votre Dolibarr :
   `htdocs/custom/modulepaie/`
2. Se connecter en administrateur.
3. Aller dans **Configuration → Modules/Applications**.
4. Activer le module **Paie (Fiches de paie françaises)**.
5. Configurer l'employeur via la page de **configuration** du module.

Alternative : créer une archive ZIP du dossier `modulepaie/` et l'installer via
**Configuration → Modules → Déployer/installer un module externe**.

## Utilisation

1. **Paie → Salariés** : créer le contrat de chaque salarié.
2. **Paie → Nouveau bulletin** : sélectionner un salarié et une période, le
   bulletin est pré-rempli avec le salaire de base et les cotisations.
3. Ajuster si besoin (primes, heures supplémentaires, absences).
4. **Valider** le bulletin puis **générer le PDF**.

## Avertissement

Les taux de cotisations fournis par défaut correspondent aux barèmes usuels
mais **doivent être vérifiés et ajustés** selon votre convention collective, la
tranche d'effectif et les taux notifiés par l'URSSAF (notamment le taux
Accident du Travail). Ce module est un outil de gestion ; il ne se substitue pas
aux conseils d'un expert-comptable.

## Licence

GPL v3 ou supérieure.
