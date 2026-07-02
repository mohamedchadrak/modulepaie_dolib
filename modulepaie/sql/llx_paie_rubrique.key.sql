-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
-- Index et clés de la table llx_paie_rubrique

ALTER TABLE llx_paie_rubrique ADD INDEX idx_paie_rubrique_entity (entity);
ALTER TABLE llx_paie_rubrique ADD INDEX idx_paie_rubrique_type (type);
ALTER TABLE llx_paie_rubrique ADD UNIQUE INDEX uk_paie_rubrique_ref_entity (ref, entity);
