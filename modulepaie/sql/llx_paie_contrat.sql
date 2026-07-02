-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Table des contrats / fiches salarié

CREATE TABLE llx_paie_contrat(
	rowid           integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity          integer DEFAULT 1 NOT NULL,
	fk_user         integer NOT NULL,
	matricule       varchar(50),
	num_secu        varchar(20),
	emploi          varchar(255),
	qualification   varchar(255),
	classification  varchar(128),
	coefficient     varchar(32),
	niveau          varchar(64),
	echelon         varchar(64),
	convention      varchar(255),
	date_entree     date,
	date_anciennete date,
	date_sortie     date,
	type_contrat    varchar(16) DEFAULT 'CDI',
	categorie       varchar(32) DEFAULT 'non_cadre',
	salaire_base    double(24,8) DEFAULT 0,
	temps_travail   double(10,4) DEFAULT 151.67,
	taux_horaire    double(24,8) DEFAULT 0,
	active          integer DEFAULT 1 NOT NULL,
	note            text,
	date_creation   datetime NOT NULL,
	tms             timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat   integer,
	fk_user_modif   integer
) ENGINE=innodb;
