-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Lignes (rubriques) des bulletins de paie

CREATE TABLE llx_paie_bulletin_ligne(
	rowid            integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_bulletin      integer NOT NULL,
	fk_rubrique      integer,
	ref              varchar(64),
	label            varchar(255) NOT NULL,
	type             varchar(32) NOT NULL DEFAULT 'cotisation',
	categorie        varchar(32) NOT NULL DEFAULT 'autres',
	nombre           double(10,4) DEFAULT 0,
	base             double(24,8) DEFAULT 0,
	taux_salarial    double(8,4) DEFAULT 0,
	montant_salarial double(24,8) DEFAULT 0,
	taux_patronal    double(8,4) DEFAULT 0,
	montant_patronal double(24,8) DEFAULT 0,
	sens             varchar(8) DEFAULT 'plus',
	soumis           integer DEFAULT 1 NOT NULL,
	imposable        integer DEFAULT 1 NOT NULL,
	position         integer DEFAULT 100 NOT NULL
) ENGINE=innodb;
