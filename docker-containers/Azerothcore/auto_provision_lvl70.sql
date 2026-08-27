--create our table
CREATE TEMPORARY TABLE lvl70_starter_items (
    class TINYINT UNSIGNED NOT NULL,
    itemid MEDIUMINT UNSIGNED NOT NULL,
    amount SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (class, itemid)
);
--items to equip
-- ====================================================================
-- AZEROTHCORE 3.3.5a LEVEL 70 STARTER GEAR
--
-- Goals:
--   * Level 70 or lower
--   * No reputation requirement
--   * No profession/crafting specialization requirement
--   * No honor/rank requirement
--   * Appropriate armor/weapon type for each class
--   * TBC-era gear
-- ====================================================================

DELETE FROM playercreateinfo_item
WHERE race = 0
  AND class IN (1,2,3,4,5,7,8,9,11);


-- ====================================================================
-- CLASS 1: WARRIOR
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 1, 30120, 1),  -- Destroyer Battle-Helm
(0, 1, 31695, 1),
(0, 1, 30122, 1),  -- Destroyer Shoulderblades
(0, 1, 27892, 1),
(0, 1, 30118, 1),  -- Destroyer Breastplate
(0, 1, 28171, 1),
(0, 1, 30119, 1),  -- Destroyer Gauntlets
(0, 1, 27985, 1),
(0, 1, 30121, 1),  -- Destroyer Greaves
(0, 1, 28176, 1),

(0, 1, 31077, 1),
(0, 1, 28323, 1),

(0, 1, 28288, 1),
(0, 1, 28034, 1),  -- Hourglass of the Unraveller

(0, 1, 28189, 1),  -- Latro's Shifting Sword
(0, 1, 31332, 1),  -- Blinkstrike - replaces Aldor Vindicator's Brand

(0, 1, 30279, 1),

(0, 1, 21841, 4);  -- Netherweave Bags


-- ====================================================================
-- CLASS 2: PALADIN
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 2, 30125, 1),  -- Crystalforge Faceguard
(0, 2, 27792, 1),
(0, 2, 30127, 1),  -- Crystalforge Shoulderguards
(0, 2, 27892, 1),
(0, 2, 30123, 1),  -- Crystalforge Chestguard
(0, 2, 29252, 1),
(0, 2, 30124, 1),  -- Crystalforge Handguards
(0, 2, 31460, 1),
(0, 2, 30126, 1),  -- Crystalforge Legguards
(0, 2, 30033, 1),

(0, 2, 11669, 1),
(0, 2, 29323, 1),

(0, 2, 30300, 1),
(0, 2, 27529, 1),

(0, 2, 32660, 1),

-- Replaces reputation-gated Petrified Lichen Guard
(0, 2, 27887, 1),  -- Platinum Shield of the Valorous

(0, 2, 32368, 1),

(0, 2, 21841, 4);


-- ====================================================================
-- CLASS 3: HUNTER
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 3, 30141, 1),  -- Rift Stalker Helm
(0, 3, 29381, 1),
(0, 3, 30143, 1),  -- Rift Stalker Mantle
(0, 3, 24259, 1),
(0, 3, 30139, 1),  -- Rift Stalker Hauberk

-- Replaces Ebon Netherscale Bracers
(0, 3, 28454, 1),  -- Stalker's War Bands

(0, 3, 30140, 1),  -- Rift Stalker Gauntlets

-- Replaces Ebon Netherscale Belt
(0, 3, 31293, 1),  -- Girdle of Gale Force

(0, 3, 30142, 1),  -- Rift Stalker Leggings
(0, 3, 25686, 1),

(0, 3, 31077, 1),
(0, 3, 30973, 1),

(0, 3, 29383, 1),  -- Bloodlust Brooch
(0, 3, 28034, 1),  -- Hourglass of the Unraveller

(0, 3, 28315, 1),
(0, 3, 27846, 1),
(0, 3, 29351, 1),

(0, 3, 21841, 4);


-- ====================================================================
-- CLASS 4: ROGUE
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 4, 30146, 1),  -- Deathmantle Helm
(0, 4, 29381, 1),
(0, 4, 30149, 1),  -- Deathmantle Shoulderpads
(0, 4, 24259, 1),
(0, 4, 30144, 1),  -- Deathmantle Chestguard

-- Replaces Primalstrike Bracers
(0, 4, 28514, 1),  -- Bracers of Maliciousness

(0, 4, 30145, 1),  -- Deathmantle Handguards

-- Replaces Primalstrike Belt
(0, 4, 28750, 1),  -- Girdle of Treachery

(0, 4, 30148, 1),  -- Deathmantle Legguards
(0, 4, 25686, 1),

(0, 4, 30860, 1),
(0, 4, 31077, 1),

(0, 4, 28034, 1),
(0, 4, 28288, 1),

-- Unrestricted level-70 weapons
(0, 4, 31332, 1),  -- Blinkstrike
(0, 4, 28189, 1),  -- Latro's Shifting Sword

(0, 4, 21841, 4);


-- ====================================================================
-- CLASS 5: PRIEST
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 5, 30152, 1),  -- Cowl of the Avatar
(0, 5, 30018, 1),
(0, 5, 30154, 1),  -- Mantle of the Avatar
(0, 5, 29989, 1),
(0, 5, 30150, 1),  -- Vestments of the Avatar
(0, 5, 32516, 1),
(0, 5, 30151, 1),  -- Gloves of the Avatar
(0, 5, 30036, 1),
(0, 5, 30153, 1),  -- Breeches of the Avatar
(0, 5, 30100, 1),

(0, 5, 30110, 1),
(0, 5, 29290, 1),

(0, 5, 29376, 1),
(0, 5, 30665, 1),

(0, 5, 30108, 1),

-- Replaces Cenarion Expedition reputation off-hand
(0, 5, 28412, 1),  -- Lamp of Peaceful Radiance

(0, 5, 30080, 1),

(0, 5, 21841, 4);


-- ====================================================================
-- CLASS 7: SHAMAN
--
-- Rebuilt accessories completely.
-- Cataclysm T5 retained.
-- Enhancement-oriented but still carries useful INT.
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 7, 30190, 1),  -- Cataclysm Helm

-- Neck
(0, 7, 28509, 1),  -- Worgen Claw Necklace

(0, 7, 30194, 1),  -- Cataclysm Shoulderplates

-- Back
(0, 7, 28672, 1),  -- Drape of the Dark Reavers

(0, 7, 30185, 1),  -- Cataclysm Chestplate

-- Wrist
(0, 7, 28454, 1),  -- Stalker's War Bands

(0, 7, 30189, 1),  -- Cataclysm Gauntlets

-- Waist
(0, 7, 31293, 1),  -- Girdle of Gale Force

(0, 7, 30192, 1),  -- Cataclysm Legplates

-- Feet
(0, 7, 28746, 1),  -- Fiend Slayer Boots

-- Rings
(0, 7, 28649, 1),  -- Garona's Signet Ring
(0, 7, 28757, 1),  -- Ring of a Thousand Marks

-- Trinkets
(0, 7, 29383, 1),  -- Bloodlust Brooch
(0, 7, 28034, 1),  -- Hourglass of the Unraveller

-- Weapon
(0, 7, 29348, 1),  -- The Bladefist

-- Shield
(0, 7, 27887, 1),  -- Platinum Shield of the Valorous

-- Totem
(0, 7, 28523, 1),  -- Totem of Healing Rains

(0, 7, 21841, 4);


-- ====================================================================
-- CLASS 8: MAGE
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 8, 30206, 1),  -- Cowl of Tirisfal
(0, 8, 30015, 1),
(0, 8, 30210, 1),  -- Mantle of Tirisfal
(0, 8, 28766, 1),
(0, 8, 30196, 1),  -- Robes of Tirisfal
(0, 8, 29918, 1),
(0, 8, 30205, 1),  -- Gloves of Tirisfal
(0, 8, 30038, 1),
(0, 8, 30207, 1),  -- Leggings of Tirisfal
(0, 8, 30037, 1),

(0, 8, 29303, 1),
(0, 8, 28753, 1),

(0, 8, 30626, 1),
(0, 8, 29370, 1),

(0, 8, 30095, 1),
(0, 8, 29982, 1),

(0, 8, 21841, 4);


-- ====================================================================
-- CLASS 9: WARLOCK
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 9, 30212, 1),  -- Hood of the Corruptor
(0, 9, 30015, 1),
(0, 9, 30215, 1),  -- Mantle of the Corruptor
(0, 9, 28766, 1),
(0, 9, 30214, 1),  -- Robe of the Corruptor
(0, 9, 29918, 1),
(0, 9, 30211, 1),  -- Gloves of the Corruptor
(0, 9, 30038, 1),
(0, 9, 30213, 1),  -- Leggings of the Corruptor
(0, 9, 30037, 1),

(0, 9, 30109, 1),
(0, 9, 29303, 1),

(0, 9, 27683, 1),
(0, 9, 29370, 1),

(0, 9, 30095, 1),
(0, 9, 30049, 1),
(0, 9, 29982, 1),

(0, 9, 21841, 4);

-- ====================================================================
-- CLASS 11: DRUID
-- ====================================================================

INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES

(0, 11, 30228, 1), -- Nordrassil Headdress
(0, 11, 30017, 1),
(0, 11, 30230, 1), -- Nordrassil Feral-Mantle
(0, 11, 29994, 1),
(0, 11, 30222, 1), -- Nordrassil Chestplate
(0, 11, 29966, 1),
(0, 11, 30223, 1), -- Nordrassil Handgrips
(0, 11, 30106, 1),
(0, 11, 30229, 1), -- Nordrassil Feral-Kilt
(0, 11, 28545, 1),

(0, 11, 30052, 1),
(0, 11, 29997, 1),

(0, 11, 29383, 1),
(0, 11, 29370, 1),

(0, 11, 30627, 1),
(0, 11, 29390, 1),
(0, 11, 27877, 1), -- Draenic Wildstaff

(0, 11, 21841, 4);
--flying mounts for everyone
INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES
(0, 1, 43951, 1),
(0, 2, 43951, 1),
(0, 3, 43951, 1),
(0, 4, 43951, 1),
(0, 5, 43951, 1),
(0, 7, 43951, 1),
(0, 8, 43951, 1),
(0, 9, 43951, 1),
(0, 11, 43951, 1);
--ground mounts for everyone
INSERT INTO playercreateinfo_item
(race, class, itemid, amount) VALUES
(0, 1, 33977, 1),
(0, 2, 33977, 1),
(0, 3, 33977, 1),
(0, 4, 33977, 1),
(0, 5, 33977, 1),
(0, 7, 33977, 1),
(0, 8, 33977, 1),
(0, 9, 33977, 1),
(0, 11, 33977, 1);
--see what will be rejected
SELECT
    s.class,
    s.itemid,
    it.name,
    it.ItemLevel,
    it.RequiredLevel,
    it.RequiredSkill,
    it.RequiredSkillRank,
    it.requiredspell,
    it.requiredhonorrank,
    it.RequiredReputationFaction,
    it.RequiredReputationRank,
    it.AllowableClass,
    it.AllowableRace,

    CASE
        WHEN it.RequiredLevel > 70
            THEN 'REQUIRES LEVEL > 70'

        WHEN it.RequiredSkill <> 0
          OR it.RequiredSkillRank <> 0
            THEN 'REQUIRES SKILL/PROFESSION'

        WHEN it.requiredspell <> 0
            THEN 'REQUIRES SPELL'

        WHEN it.requiredhonorrank <> 0
            THEN 'REQUIRES HONOR RANK'

        WHEN it.RequiredReputationFaction <> 0
            THEN 'REQUIRES REPUTATION'

        WHEN it.AllowableClass <> -1
          AND (
              it.AllowableClass
              & (1 << (s.class - 1))
          ) = 0
            THEN 'WRONG CLASS'

        /*
         * Required race masks for WotLK class/race combinations:
         *
         * Warrior = 1279
         * Paladin = 1541
         * Hunter  = 1710
         * Rogue   = 735
         * Priest  = 1693
         * Shaman  = 1186
         * Mage    = 1745
         * Warlock = 595
         * Druid   = 40
         */
        WHEN it.AllowableRace <> -1
         AND (
             it.AllowableRace
             & CASE s.class
                 WHEN 1  THEN 1279
                 WHEN 2  THEN 1541
                 WHEN 3  THEN 1710
                 WHEN 4  THEN 735
                 WHEN 5  THEN 1693
                 WHEN 7  THEN 1186
                 WHEN 8  THEN 1745
                 WHEN 9  THEN 595
                 WHEN 11 THEN 40
               END
         ) <> CASE s.class
                 WHEN 1  THEN 1279
                 WHEN 2  THEN 1541
                 WHEN 3  THEN 1710
                 WHEN 4  THEN 735
                 WHEN 5  THEN 1693
                 WHEN 7  THEN 1186
                 WHEN 8  THEN 1745
                 WHEN 9  THEN 595
                 WHEN 11 THEN 40
               END
            THEN 'NOT VALID FOR EVERY CLASS RACE'

        ELSE 'OK'
    END AS result

FROM playercreateinfo_item s
JOIN item_template it
    ON it.entry = s.itemid

HAVING result <> 'OK'

ORDER BY
    s.class,
    s.itemid;

--migrate into db as needed:
INSERT INTO playercreateinfo_item
    (race, class, itemid, amount)
SELECT
    0,
    s.class,
    s.itemid,
    s.amount
FROM lvl70_starter_items s
JOIN item_template it
    ON it.entry = s.itemid
WHERE it.RequiredLevel <= 70

  -- No crafting/profession requirement
  AND it.RequiredSkill = 0
  AND it.RequiredSkillRank = 0

  -- No special spell prerequisite
  AND it.requiredspell = 0

  -- No PvP/honor rank
  AND it.requiredhonorrank = 0

  -- No reputation requirement
  AND it.RequiredReputationFaction = 0

  -- Must actually be usable by the class
  AND (
      it.AllowableClass = -1
      OR (it.AllowableClass & (1 << (s.class - 1))) <> 0
  )

  -- Because race=0 means you're giving it to every race
AND (
    it.AllowableRace = -1
    OR
    (
        it.AllowableRace
        & CASE s.class
            WHEN 1  THEN 1279
            WHEN 2  THEN 1541
            WHEN 3  THEN 1710
            WHEN 4  THEN 735
            WHEN 5  THEN 1693
            WHEN 7  THEN 1186
            WHEN 8  THEN 1745
            WHEN 9  THEN 595
            WHEN 11 THEN 40
          END
    ) =
    CASE s.class
        WHEN 1  THEN 1279
        WHEN 2  THEN 1541
        WHEN 3  THEN 1710
        WHEN 4  THEN 735
        WHEN 5  THEN 1693
        WHEN 7  THEN 1186
        WHEN 8  THEN 1745
        WHEN 9  THEN 595
        WHEN 11 THEN 40
    END
)