-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
-- Index et clés de la table llx_paie_bulletin

ALTER TABLE llx_paie_bulletin ADD INDEX idx_paie_bulletin_fk_user (fk_user);
ALTER TABLE llx_paie_bulletin ADD INDEX idx_paie_bulletin_entity (entity);
ALTER TABLE llx_paie_bulletin ADD INDEX idx_paie_bulletin_status (status);
ALTER TABLE llx_paie_bulletin ADD UNIQUE INDEX uk_paie_bulletin_ref_entity (ref, entity);
