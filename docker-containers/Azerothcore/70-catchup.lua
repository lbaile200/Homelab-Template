-- lvl70_catchup_master.lua
--
-- AzerothCore / ALE
-- WoW WotLK 3.3.5a
--
-- First-login catch-up for level 70 characters.
--
-- Does:
--   * Appropriate class weapon proficiencies
--   * Appropriate armor proficiencies
--   * Maxes weapon skills to level-appropriate maximum
--   * Gives TBC-level secondary professions
--   * Gives TBC riding through Artisan
--   * Gives appropriate Classic/TBC reputations
--   * Initializes appropriate taxi nodes
--
-- Does NOT:
--   * Complete quests
--   * Reward quests
--   * Learn talents
--   * Grant Cold Weather Flying
--   * Grant cross-faction reputations

local PLAYER_EVENT_ON_FIRST_LOGIN = 30

local START_LEVEL = 70

-- TBC profession maximum is 375.
-- Level 70 weapon skill maximum is 350, but AdvanceSkillsToMax()
-- handles the correct cap automatically.
local MAX_TBC_PROFESSION_SKILL = 375


------------------------------------------------------------
-- Optional Shattrath alignment
--
-- Valid:
--   nil
--   "ALDOR"
--   "SCRYER"
--
-- Aldor and Scryers are opposing factions, so DO NOT
-- automatically exalt both.
------------------------------------------------------------

local SHATTRATH_ALIGNMENT = nil


------------------------------------------------------------
-- Weapon proficiency spell IDs
--
-- AzerothCore mappings:
--
-- 196  = One-Handed Axes
-- 197  = Two-Handed Axes
-- 198  = One-Handed Maces
-- 199  = Two-Handed Maces
-- 200  = Polearms
-- 201  = One-Handed Swords
-- 202  = Two-Handed Swords
-- 227  = Staves
-- 264  = Bows
-- 266  = Guns
-- 1180 = Daggers
-- 2567 = Thrown
-- 5009 = Wands
-- 5011 = Crossbows
-- 15590 = Fist Weapons
------------------------------------------------------------

local CLASS_WEAPONS = {

    -- Warrior
    [1] = {
        196,   -- One-Handed Axes
        197,   -- Two-Handed Axes
        198,   -- One-Handed Maces
        199,   -- Two-Handed Maces
        200,   -- Polearms
        201,   -- One-Handed Swords
        202,   -- Two-Handed Swords
        227,   -- Staves
        1180,  -- Daggers
        15590, -- Fist Weapons
        264,   -- Bows
        266,   -- Guns
        5011,  -- Crossbows
        2567,  -- Thrown
    },

    -- Paladin
    [2] = {
        196,   -- One-Handed Axes
        197,   -- Two-Handed Axes
        198,   -- One-Handed Maces
        199,   -- Two-Handed Maces
        200,   -- Polearms
        201,   -- One-Handed Swords
        202,   -- Two-Handed Swords
    },

    -- Hunter
    [3] = {
        196,   -- One-Handed Axes
        197,   -- Two-Handed Axes
        200,   -- Polearms
        201,   -- One-Handed Swords
        202,   -- Two-Handed Swords
        227,   -- Staves
        1180,  -- Daggers
        15590, -- Fist Weapons
        264,   -- Bows
        266,   -- Guns
        5011,  -- Crossbows
    },

    -- Rogue
    [4] = {
        196,   -- One-Handed Axes
        198,   -- One-Handed Maces
        201,   -- One-Handed Swords
        1180,  -- Daggers
        15590, -- Fist Weapons
        264,   -- Bows
        266,   -- Guns
        5011,  -- Crossbows
        2567,  -- Thrown
    },

    -- Priest
    [5] = {
        198,   -- One-Handed Maces
        227,   -- Staves
        1180,  -- Daggers
        5009,  -- Wands
    },

    -- Shaman
    [7] = {
        196,   -- One-Handed Axes
        197,   -- Two-Handed Axes
        198,   -- One-Handed Maces
        199,   -- Two-Handed Maces
        227,   -- Staves
        1180,  -- Daggers
        15590, -- Fist Weapons
    },

    -- Mage
    [8] = {
        201,   -- One-Handed Swords
        227,   -- Staves
        1180,  -- Daggers
        5009,  -- Wands
    },

    -- Warlock
    [9] = {
        201,   -- One-Handed Swords
        227,   -- Staves
        1180,  -- Daggers
        5009,  -- Wands
    },

    -- Druid
    [11] = {
        198,   -- One-Handed Maces
        199,   -- Two-Handed Maces
        200,   -- Polearms
        227,   -- Staves
        1180,  -- Daggers
        15590, -- Fist Weapons
    },
}


------------------------------------------------------------
-- Armor proficiencies required by class progression
--
-- 750  = Plate
-- 8737 = Mail
-- 9116 = Shield
------------------------------------------------------------

local CLASS_ARMOR = {

    -- Warrior
    [1] = {
        750,   -- Plate
        9116,  -- Shield
    },

    -- Paladin
    [2] = {
        750,   -- Plate
        9116,  -- Shield
    },

    -- Hunter
    [3] = {
        8737,  -- Mail
    },

    -- Shaman
    [7] = {
        8737,  -- Mail
        9116,  -- Shield
    },
}


------------------------------------------------------------
-- Shared Classic / TBC reputations
--
-- These are available to both factions.
------------------------------------------------------------

local SHARED_REPUTATIONS = {

    -- Classic
    529,   -- Argent Dawn
    576,   -- Timbermaw Hold
    609,   -- Cenarion Circle
    910,   -- Brood of Nozdormu

    -- Burning Crusade
    933,   -- The Consortium
    935,   -- The Sha'tar
    942,   -- Cenarion Expedition
    967,   -- The Violet Eye
    970,   -- Sporeggar
    989,   -- Keepers of Time
    990,   -- The Scale of the Sands
    1011,  -- Lower City
    1012,  -- Ashtongue Deathsworn
    1015,  -- Netherwing
    1031,  -- Sha'tari Skyguard
    1038,  -- Ogri'la
    1077,  -- Shattered Sun Offensive
}


------------------------------------------------------------
-- Faction-specific reputations
--
-- GetTeam():
--   0 = Alliance
--   1 = Horde
------------------------------------------------------------

local ALLIANCE_REPUTATIONS = {
    946, -- Honor Hold
    978, -- Kurenai
}

local HORDE_REPUTATIONS = {
    947, -- Thrallmar
    941, -- The Mag'har
}


------------------------------------------------------------
-- Shattrath alignment
------------------------------------------------------------

local ALDOR_FACTION   = 932
local SCRYER_FACTION  = 934


------------------------------------------------------------
-- Secondary professions
--
-- Skill IDs:
--   129 = First Aid
--   185 = Cooking
--   356 = Fishing
--
-- Highest TBC rank spells:
--   27028 = Master First Aid
--   33359 = Master Cooking
--   33095 = Master Fishing
------------------------------------------------------------

local SECONDARY_PROFESSIONS = {

    {
        skill = 129,
        spell = 27028,
    },

    {
        skill = 185,
        spell = 33359,
    },

    {
        skill = 356,
        spell = 33095,
    },
}


------------------------------------------------------------
-- Riding
--
-- These are the actual riding skill spells.
--
-- 33388 = Apprentice Riding
-- 33391 = Journeyman Riding
-- 34090 = Expert Riding
-- 34091 = Artisan Riding
--
-- Cold Weather Flying is 54197 and is intentionally NOT
-- granted because a normal level-70 character should not
-- have it yet.
------------------------------------------------------------

local RIDING_SPELLS = {
    33388,
    33391,
    34090,
    34091,
}


------------------------------------------------------------
-- Helper: safely learn a list of spells
------------------------------------------------------------

local function LearnSpellList(player, spells)

    if not spells then
        return
    end

    for _, spellId in ipairs(spells) do

        if not player:HasSpell(spellId) then
            player:LearnSpell(spellId)
        end

    end
end


------------------------------------------------------------
-- Weapon / armor proficiencies
------------------------------------------------------------

local function GiveClassProficiencies(player)

    local classId = player:GetClass()

    LearnSpellList(
        player,
        CLASS_WEAPONS[classId]
    )

    LearnSpellList(
        player,
        CLASS_ARMOR[classId]
    )

    -- Specifically advances weapon skills to their maximum
    -- allowed value for the player's current level.
    --
    -- At level 70 this means 350.
    player:AdvanceSkillsToMax()
end


------------------------------------------------------------
-- Secondary professions
------------------------------------------------------------

local function GiveSecondaryProfessions(player)

    for _, profession in ipairs(SECONDARY_PROFESSIONS) do

        if not player:HasSpell(profession.spell) then
            player:LearnSpell(profession.spell)
        end

        -- TBC Master rank = step 5, max 375.
        player:SetSkill(
            profession.skill,
            5,
            MAX_TBC_PROFESSION_SKILL,
            MAX_TBC_PROFESSION_SKILL
        )

    end
end


------------------------------------------------------------
-- Riding
------------------------------------------------------------

local function GiveRiding(player)

    LearnSpellList(
        player,
        RIDING_SPELLS
    )

end


------------------------------------------------------------
-- Reputation
------------------------------------------------------------

local function GiveReputations(player)

    -- Shared reputations
    for _, factionId in ipairs(SHARED_REPUTATIONS) do
        player:SetReputation(
            factionId,
            42000
        )
    end


    -- Faction-specific reputations
    local team = player:GetTeam()

    if team == 0 then

        for _, factionId in ipairs(ALLIANCE_REPUTATIONS) do
            player:SetReputation(
                factionId,
                42000
            )
        end

    elseif team == 1 then

        for _, factionId in ipairs(HORDE_REPUTATIONS) do
            player:SetReputation(
                factionId,
                42000
            )
        end

    end


    -- Optional Aldor / Scryer choice
    if SHATTRATH_ALIGNMENT == "ALDOR" then

        player:SetReputation(
            ALDOR_FACTION,
            42000
        )

    elseif SHATTRATH_ALIGNMENT == "SCRYER" then

        player:SetReputation(
            SCRYER_FACTION,
            42000
        )

    end
end


------------------------------------------------------------
-- First login
------------------------------------------------------------

local function OnFirstLogin(event, player)

    --------------------------------------------------------
    -- Death Knights excluded
    --------------------------------------------------------

    if player:GetClass() == 6 then
        return
    end


    --------------------------------------------------------
    -- Only process level-70+ catch-up characters
    --------------------------------------------------------

    if player:GetLevel() < START_LEVEL then
        return
    end


    --------------------------------------------------------
    -- 1. Class weapon / armor proficiencies
    --------------------------------------------------------

    GiveClassProficiencies(player)


    --------------------------------------------------------
    -- 2. Secondary professions
    --------------------------------------------------------

    GiveSecondaryProfessions(player)


    --------------------------------------------------------
    -- 3. TBC riding
    --------------------------------------------------------

    GiveRiding(player)


    --------------------------------------------------------
    -- 4. Appropriate reputations
    --------------------------------------------------------

    GiveReputations(player)


    --------------------------------------------------------
    -- 5. Initialize normal taxi nodes for the character
    --
    -- This uses AzerothCore's taxi system rather than
    -- teaching an undocumented/helper spell.
    --------------------------------------------------------

    player:InitTaxiNodesForLevel()


    --------------------------------------------------------
    -- Player notification
    --------------------------------------------------------

    player:SendAreaTriggerMessage(
        "Welcome! Level 70 catch-up complete. Have fun in Northrend!"
    )

    print(string.format(
        "[Lvl70Catchup] Applied catch-up to %s (class %d, team %d)",
        player:GetName(),
        player:GetClass(),
        player:GetTeam()
    ))
end


------------------------------------------------------------
-- First-login only
------------------------------------------------------------

RegisterPlayerEvent(
    PLAYER_EVENT_ON_FIRST_LOGIN,
    OnFirstLogin
)
