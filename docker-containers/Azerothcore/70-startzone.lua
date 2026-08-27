local function OnFirstLogin(event, player)

    if player:GetClass() == 6 then
        return
    end

    if player:GetLevel() < 70 then
        return
    end

    if player:GetTeam() == 0 then
        -- Alliance: Valgarde
        player:Teleport(
            571,
            582.5,
            -5100.5,
            5.2,
            4.8
        )

    elseif player:GetTeam() == 1 then
        -- Horde: Vengeance Landing
        player:Teleport(
            571,
            2854.6,
            6188.7,
            121.2,
            3.5
        )
    end

    player:SendBroadcastMessage(
        "|cff00ff00[Server]:|r Welcome to Northrend!"
    )
end

RegisterPlayerEvent(30, OnFirstLogin)
