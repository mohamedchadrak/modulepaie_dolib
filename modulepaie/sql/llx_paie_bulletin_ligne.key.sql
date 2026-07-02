-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
-- Index et clés de la table llx_paie_bulletin_ligne

ALTER TABLE llx_paie_bulletin_ligne ADD INDEX idx_paie_bulletin_ligne_fk_bulletin (fk_bulletin);
ALTER TABLE llx_paie_bulletin_ligne ADD CONSTRAINT fk_paie_bulletin_ligne_bulletin FOREIGN KEY (fk_bulletin) REFERENCES llx_paie_bulletin (rowid);
