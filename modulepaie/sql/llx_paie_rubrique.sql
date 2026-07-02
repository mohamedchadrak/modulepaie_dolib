-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Catalogue des rubriques de paie (gains et cotisations)

CREATE TABLE llx_paie_rubrique(
	rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity         integer DEFAULT 1 NOT NULL,
	ref            varchar(64) NOT NULL,
	label          varchar(255) NOT NULL,
	-- type: gain, cotisation, net, info
	type           varchar(32) NOT NULL DEFAULT 'cotisation',
	-- categorie legale (bulletin clarifie): sante, atmp, retraite, famille, chomage, csgcrds, autres, gain, allegement, net
	categorie      varchar(32) NOT NULL DEFAULT 'autres',
	-- mode de calcul de la base: brut, plafond_ss, tranche1, tranche2, tranche_a, tranche_b, csg, fixe, manuel
	base_type      varchar(32) NOT NULL DEFAULT 'brut',
	base_fixe      double(24,8) DEFAULT 0,
	taux_salarial  double(8,4) DEFAULT 0,
	taux_patronal  double(8,4) DEFAULT 0,
	-- sens: plus (ajoute au net) ou moins (retire du net) - pour les gains/retenues
	sens           varchar(8) DEFAULT 'plus',
	-- 1 si le gain est soumis a cotisations et entre dans le brut
	soumis         integer DEFAULT 1 NOT NULL,
	-- 1 si le montant est imposable (entre dans le net imposable)
	imposable      integer DEFAULT 1 NOT NULL,
	position       integer DEFAULT 100 NOT NULL,
	active         integer DEFAULT 1 NOT NULL,
	note           text,
	date_creation  datetime NOT NULL,
	tms            timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat  integer,
	fk_user_modif  integer
) ENGINE=innodb;
