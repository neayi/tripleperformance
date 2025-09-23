CREATE TABLE pagelinks_dedup AS
SELECT DISTINCT pl_from, pl_from_namespace, pl_target_id
FROM pagelinks
GROUP BY pl_from, pl_from_namespace, pl_target_id;

-- 2. Vider la table originale
TRUNCATE TABLE pagelinks;

-- 3. Réinjecter les données propres
INSERT INTO pagelinks (pl_from, pl_from_namespace, pl_target_id)
SELECT pl_from, pl_from_namespace, pl_target_id
FROM pagelinks_dedup;

-- 4. Supprimer la table temporaire
DROP TABLE pagelinks_dedup;
