-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
-- Index et clés de la table llx_paie_contrat

ALTER TABLE llx_paie_contrat ADD INDEX idx_paie_contrat_fk_user (fk_user);
ALTER TABLE llx_paie_contrat ADD INDEX idx_paie_contrat_entity (entity);
ALTER TABLE llx_paie_contrat ADD UNIQUE INDEX uk_paie_contrat_user_entity (fk_user, entity);
