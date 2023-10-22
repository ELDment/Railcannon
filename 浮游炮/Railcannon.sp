#include <sourcemod>
#include <sdktools>
#include <sdkhooks>
#include <cstrike>
#include <ripext>
#pragma newdecls required
#pragma semicolon 1

int g_beamsprite;
int g_iRCIdx[MAXPLAYERS + 1];
int g_rcColor[MAXPLAYERS + 1][3];
int g_unix[MAXPLAYERS + 1];
float g_flRCLastThink[MAXPLAYERS + 1];
bool g_bLoaded[MAXPLAYERS + 1];
// cfg
char RCwebapi[256];
char RCmaterial[256];
char RCprefix[64];

ConVar RcApi;
ConVar RcMaterial;
ConVar RcPrefix;
// 云验证
static const char CloudServer[] = "http://43.143.135.75:7891/SourceMod/Railcannon.php";

public Plugin myinfo = 
{
	name = "Railcannon",
	author = "ELDment",
	description = "Railcannon",
	version = "Internal",
	url = "http://github.com/ELDment"
};

public void OnPluginStart()
{
	RcApi = CreateConVar("Rc_Api", "浮游炮API接口", "例如: https://abc.com/api.php 或 http://127.0.0.1:80/api.php", _, false, _, false, _);
	RcMaterial = CreateConVar("Rc_Vtm", "浮游炮弹道材质路径", "例如: materials/sprites/trails/bluelightning.vmt", _, false, _, false, _);
	RcPrefix = CreateConVar("Rc_Prx", "浮游炮输出前缀", "例如: Railcannon", _, false, _, false, _);
	AutoExecConfig(true, "Railcannon");
	RcApi.AddChangeHook(OnConVarChanged);
	RcMaterial.AddChangeHook(OnConVarChanged);
	RcPrefix.AddChangeHook(OnConVarChanged);
	
	RegConsoleCmd("sm_rcl", Change_Color);
	RegConsoleCmd("sm_rc", RC_Menu);
	RegConsoleCmd("sm_frc", ForceRefresh);
	RegConsoleCmd("sm_rckey", ExChangeRC);
	HookEvent("bullet_impact", Event_OnBulletImpact, EventHookMode_Post);
	HookEvent("player_death", Event_OnPlayerDeath, EventHookMode_Post);
	HookEvent("round_start", Event_OnRoundStart, EventHookMode_PostNoCopy);
}

public void OnConfigsExecuted()
{
	PluginVerify();
	RcApi.GetString(RCwebapi, sizeof RCwebapi);
	RcMaterial.GetString(RCmaterial, sizeof RCmaterial);
	RcPrefix.GetString(RCprefix, sizeof RCprefix);
	// 预加载
	g_beamsprite = PrecacheModel(RCmaterial);
}

public void OnConVarChanged(ConVar convar, const char[] oldValue, const char[] newValue)
{
	if (convar == RcApi)	
	{
		RcApi.GetString(RCwebapi, sizeof RCwebapi);
	}
	else if (convar == RcMaterial)
	{
		RcMaterial.GetString(RCmaterial, sizeof RCmaterial);
	}
	else if (convar == RcPrefix)
		RcPrefix.GetString(RCprefix, sizeof RCprefix);
}

public void OnClientPostAdminCheck(int client)
{
	if (IsValidClient(client))
	{
		PluginVerify();
		g_iRCIdx[client] = -1;
		g_flRCLastThink[client] = 0.0;
		g_bLoaded[client] = false;
		Http(client, 0, 0, 0, 0);
	}
}

public Action ForceRefresh(int client, int args)
{
	if (IsValidClient(client))
		Http(client, 0, 0, 0, 0);
	return Plugin_Handled;
}

public Action ExChangeRC(int client, int arg)
{
	if (arg != 1)
	{
		PrintToChat(client, "\x01[\x0E%s\x01] 浮游炮卡密\x07不能为空", RCprefix);
		return Plugin_Handled;
	}
	char key[128];
	GetCmdArg(1, key, sizeof key);
	if (IsValidClient(client))
		Http(client, 2, 0, 0, 0, key);
	return Plugin_Continue;
}

public void OnClientDisconnect(int client)
{
	if (g_iRCIdx[client] != -1)
	{
		if (IsValidEdict(g_iRCIdx[client]))
		{
			AcceptEntityInput(g_iRCIdx[client], "Kill");
			g_iRCIdx[client] = -1;
			g_flRCLastThink[client] = 0.0;
		}
	}
}

public Action RC_Menu(int client, int args)
{
	char time[128];
	char rgb[64];
	if (g_unix[client] > 0)
	{
		FormatTime(time, sizeof(time), "%Y-%m-%d %H:%M:%S", g_unix[client]);
		Format(time, sizeof(time), "浮游炮到期时间：%s", time);
		Format(rgb, sizeof(rgb), "当前设定RGB：%i, %i, %i", g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2]);
	}else{
		Format(time, sizeof(time), "未获得浮游炮使用权");
		Format(rgb, sizeof(rgb), "无设定RGB值");
	}
	Handle menu = CreateMenu(MenuHandle);
	SetMenuTitle(menu, "浮游炮管理面板");
	AddMenuItem(menu, "1", time, ITEMDRAW_DISABLED);
	AddMenuItem(menu, "2", rgb, ITEMDRAW_DISABLED);
	AddMenuItem(menu, "3", "启动浮游炮(当浮游炮自动开启失败时)  !frc", ITEMDRAW_DISABLED);
	AddMenuItem(menu, "4", "更改浮游炮设定颜色  !rcl (int)R (int)G (int)B", ITEMDRAW_DISABLED);
	AddMenuItem(menu, "5", "兑换浮游炮  !rckey (str)KEY", ITEMDRAW_DISABLED);
	SetMenuExitButton(menu, true);
	DisplayMenu(menu, client, 30);
	return Plugin_Handled;
}

public int MenuHandle(Menu menu, MenuAction action, int param1, int selection)
{
	if (action == MenuAction_Select)
	{
		return 0;
	}
	return 0;
}

public Action Change_Color(int client, int args)
{
	if (args != 3)
	{
		PrintToChat(client, "\x01[\x0E%s\x01] \x07无效\x01RGB设定\x04使用实例:\x01 !rcl 255 15 20", RCprefix);
		return Plugin_Continue;
	}
	if (!g_bLoaded[client])
	{
		PrintToChat(client, "\x01[\x0E%s\x01] \x01未获得浮游炮使用权", RCprefix);
		return Plugin_Continue;
	}
	char Rbuffer[8];
	GetCmdArg(1, Rbuffer, sizeof(Rbuffer));
	char Gbuffer[8];
	GetCmdArg(2, Gbuffer, sizeof(Gbuffer));
	char Bbuffer[8];
	GetCmdArg(3, Bbuffer, sizeof(Bbuffer));
	int R = StringToInt(Rbuffer);
	int G = StringToInt(Gbuffer);
	int B = StringToInt(Bbuffer);
	g_rcColor[client][0] = R;
	g_rcColor[client][1] = G;
	g_rcColor[client][2] = B;
	SetEntityRenderColor(g_iRCIdx[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2], 50);
	SetGlowColor(g_iRCIdx[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2], 255);
	Http(client, 1, R, G, B);
	return Plugin_Handled;
}

public Action Railcannon(int client)
{
	int weapon = GetEntPropEnt(client, Prop_Send, "m_hActiveWeapon");
	if (!IsValidEdict(weapon))
		return Plugin_Continue;
	float vecOrigin[3];
	char szModel[256];
	GetClientEyePosition(client, vecOrigin);
	vecOrigin[2] += 35.0;
	GetEntPropString(weapon, Prop_Data, "m_ModelName", szModel, 256);
	ReplaceString(szModel, 256, "_dropped", "", false);
	g_iRCIdx[client] = CreateEntityByName("prop_dynamic_glow");
	if(g_iRCIdx[client] == -1)
		return Plugin_Continue;

	DispatchKeyValue(g_iRCIdx[client], "model", szModel);
	DispatchKeyValue(g_iRCIdx[client], "disablereceiveshadows", "1");
	DispatchKeyValue(g_iRCIdx[client], "disableshadows", "1");
	DispatchKeyValue(g_iRCIdx[client], "solid", "0");
	DispatchKeyValue(g_iRCIdx[client], "spawnflags", "256");
	DispatchSpawn(g_iRCIdx[client]);
	SetEntProp(g_iRCIdx[client], Prop_Send, "m_CollisionGroup", 11);
	SetEntProp(g_iRCIdx[client], Prop_Send, "m_bShouldGlow", true);
	SetEntPropFloat(g_iRCIdx[client], Prop_Send, "m_flModelScale", 2.0);
	SetEntPropFloat(g_iRCIdx[client], Prop_Send, "m_flGlowMaxDist", 100000.0);
	SetEntityRenderMode(g_iRCIdx[client],  RENDER_TRANSCOLOR);
	SetEntityRenderColor(g_iRCIdx[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2], 50);
	SetGlowColor(g_iRCIdx[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2], 255);
	TeleportEntity(g_iRCIdx[client], vecOrigin, NULL_VECTOR, NULL_VECTOR);
	SetEntPropEnt(g_iRCIdx[client], Prop_Send, "m_hOwnerEntity", client);
	SetEntPropEnt(g_iRCIdx[client], Prop_Data, "m_pParent", client);

	SDKHook(client, SDKHook_WeaponSwitchPost, SDKHookCB_WeaponSwitchPost);
	return Plugin_Continue;
}

public void OnGameFrame()
{
	for (int client = 1; client <= MaxClients; client++)
	{
		if (IsValidClient(client))
		{
			if (g_iRCIdx[client] != -1)
			{
				if (GetGameTime() >= g_flRCLastThink[client])
				{
					if (IsValidEdict(g_iRCIdx[client]))
					{
						float vecAngle[3];
						GetEntPropVector(g_iRCIdx[client], Prop_Send, "m_angRotation", vecAngle);
						vecAngle[0] = 0.0;
						vecAngle[1] += 5.0;
						float vecOrigin[3];
						GetClientEyePosition(client, vecOrigin);
						if(GetClientButtons(client) & IN_DUCK)
						{
							vecOrigin[2] += 30.0;
						}
						else
							vecOrigin[2] += 47.0;
						TeleportEntity(g_iRCIdx[client], vecOrigin, vecAngle, NULL_VECTOR);
					}else{
						g_iRCIdx[client] = -1;
						g_flRCLastThink[client] = 0.0;
					}
				}
			}
		}else{
			SDKUnhook(client, SDKHook_WeaponSwitchPost, SDKHookCB_WeaponSwitchPost);
		}
	}
}

public void SDKHookCB_WeaponSwitchPost(int client, int weapon) 
{ 
	if (!IsValidEdict(weapon))
		return;
	if (!IsValidEdict(g_iRCIdx[client]))
		return;
	char szModel[256];
	GetEntPropString(weapon, Prop_Data, "m_ModelName", szModel, 256);
	ReplaceString(szModel, 256, "_dropped", "", false);
	SetEntityModel(g_iRCIdx[client], szModel);
	
	if 	(	StrContains(szModel, "ied") != -1 ||
				StrContains(szModel, "taser") != -1 ||
				StrContains(szModel, "knife") != -1 ||
				StrContains(szModel, "smokegrenade") != -1 ||
				StrContains(szModel, "fraggrenade") != -1 ||
				StrContains(szModel, "flashbang") != -1 ||
				StrContains(szModel, "decoy") != -1 ||
				StrContains(szModel, "molotov") != -1 ||
				StrContains(szModel, "incendiarygrenade") != -1
			){
		SetEntityRenderMode(g_iRCIdx[client], RENDER_NONE);
		SetEntityRenderColor(g_iRCIdx[client], 255, 255, 255, 0);
		SetGlowColor(g_iRCIdx[client], 255, 255, 255, 0);
	}else{
		SetEntityRenderMode(g_iRCIdx[client], RENDER_TRANSCOLOR);
		SetEntityRenderColor(g_iRCIdx[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2], 50);
		SetGlowColor(g_iRCIdx[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2], 255);
	}
}

public Action Event_OnBulletImpact(Event event, const char[] name, bool dontBroadcast) 
{
	int client = GetClientOfUserId(event.GetInt("userid"));
	if (IsValidClient(client))
	{
		if (g_iRCIdx[client] != -1)
		{
			if (IsValidEdict(g_iRCIdx[client]))
			{
				float vBullet[3];
				vBullet[0] = event.GetFloat("x");
				vBullet[1] = event.GetFloat("y");
				vBullet[2] = event.GetFloat("z");
				SniperCreateBeam(client, vBullet);
				g_flRCLastThink[client] = GetGameTime() + 0.3;
			}
		}
	}
	return Plugin_Continue;
}

public Action Event_OnRoundStart(Event event, const char[] name, bool dontBroadcast)
{
	for (int i = 1; i <= MAXPLAYERS; i++)
	{
		g_iRCIdx[i] = -1;
		g_flRCLastThink[i] = 0.0;
		if (IsValidClient(i))
		{
			SDKUnhook(i, SDKHook_WeaponSwitchPost, SDKHookCB_WeaponSwitchPost);
			if (g_bLoaded[i])
				Railcannon(i);
		}
	}
	return Plugin_Continue;
}

public Action Event_OnPlayerDeath(Event event, const char[] name, bool dontBroadcast) 
{
	int client = GetClientOfUserId(event.GetInt("userid"));
	if (g_iRCIdx[client] != -1)
	{
		if (IsValidEdict(g_iRCIdx[client]))
		{
			AcceptEntityInput(g_iRCIdx[client], "Kill");
			g_iRCIdx[client] = -1;
			g_flRCLastThink[client] = 0.0;
		}
	}
	return Plugin_Continue;
}

public void SniperCreateBeam(int client, float vBullet[3])
{
	float vecOrigin[3];
	GetClientEyePosition(client, vecOrigin);
	if(GetClientButtons(client) & IN_DUCK)
	{
		vecOrigin[2] += 30.0;
	}
	else
		vecOrigin[2] += 47.0;
	TeleportEntity(g_iRCIdx[client], vecOrigin, NULL_VECTOR, NULL_VECTOR);
	float vRCOrigin[3];
	GetEntPropVector(g_iRCIdx[client], Prop_Send, "m_vecOrigin", vRCOrigin);
	float distance = GetVectorDistance( vRCOrigin, vBullet);
	float percentage = 0.4 / (distance / 100);
	float vnewRCOrigin[3];
	vnewRCOrigin[0] = vRCOrigin[0] + ( ( vBullet[0] - vRCOrigin[0] ) * percentage );
	vnewRCOrigin[1] = vRCOrigin[1] + ( ( vBullet[1] - vRCOrigin[1] ) * percentage );
	vnewRCOrigin[2] = vRCOrigin[2] + ( ( vBullet[2] - vRCOrigin[2] ) * percentage );
	float vBulletTrace[3]; float vRCAngle[3];
	MakeVectorFromPoints(vRCOrigin, vBullet, vBulletTrace);
	GetVectorAngles(vBulletTrace, vRCAngle);
	SetEntPropVector(g_iRCIdx[client], Prop_Send, "m_angRotation", vRCAngle);
	int color[4] = {255,255,255,220};
	color[0] = g_rcColor[client][0];
	color[1] = g_rcColor[client][1];
	color[2] = g_rcColor[client][2];
	TE_SetupBeamPoints(vnewRCOrigin, vBullet, g_beamsprite, 0, 0, 8, 3.0, 35.0, 1.2, 5, 0.0, color, 3);
	TE_SendToAll();
}

static void SetGlowColor(int entity, int r, int g, int b, int a)
{
	int colors[4];
	colors[0] = r;
	colors[1] = g;
	colors[2] = b;
	colors[3] = a;
	SetVariantColor(colors);
	AcceptEntityInput(entity, "SetGlowColor");
}

public bool IsValidClient(int client) 
{
	if (client > 0 && client <= MaxClients)
	{
		if (IsClientConnected(client) && IsClientInGame(client) && !IsFakeClient(client) && !IsClientSourceTV(client))
		{
			return true;
		}		
		else
			return false;
	}else{
		return false;
	}
}

stock void Http(int client, int type, int r, int g, int b, char[] key = NULL_STRING)
{
	char szSteam32[32];
	GetClientAuthId(client, AuthId_Steam2, szSteam32, 32);
	ReplaceString(szSteam32, 32, "STEAM_0:", "", false);
	ReplaceString(szSteam32, 32, "STEAM_1:", "", false);
	char api[256];
	HTTPRequest request;
	if (type == 0)
	{
		Format(api, sizeof(api), "%s?Steam32=%s", RCwebapi, szSteam32);
		request = new HTTPRequest(api);
		request.Get(DataCallback, client);
	}
	else if (type == 1)
	{
		Format(api, sizeof(api), "%s?Steam32=%s&Operate=Update&R=%i&G=%i&B=%i", RCwebapi, szSteam32, r, g, b);
		request = new HTTPRequest(api);
		request.Get(UpdateCallback, client);
	}
	else if (type == 2)
	{
		Format(api, sizeof(api), "%s?Steam32=%s&Name=%N&Operate=CDKey&Key=%s", RCwebapi, szSteam32, client, key);
		request = new HTTPRequest(api);
		request.Get(ExchangeCallback, client);
	}
}

public void DataCallback(HTTPResponse response, int client)
{
	if (response.Status != HTTPStatus_OK)
	{
		if (IsClientInGame(client))
			PrintToChat(client, "\x01[\x0E%s\x01] 获取浮游炮设定\x07失败 \x01(API未响应), \x04请稍后\x01重试。", RCprefix);
		return;
	}
	if (response.Data == null)
	{
		if (IsClientInGame(client))
			PrintToChat(client, "\x01[\x0E%s\x01] 获取浮游炮设定\x07失败 \x01(返回值为空), \x04请稍后\x01重试。", RCprefix);
		return;
	}
	JSONObject json = view_as<JSONObject>(response.Data);
	if (IsValidClient(client))
	{
		g_bLoaded[client] = view_as<bool>(json.GetBool("RailCannon"));
		g_unix[client] = view_as<int>(json.GetInt("Unix"));
		g_rcColor[client][0] = view_as<int>(json.GetInt("R"));
		g_rcColor[client][1] = view_as<int>(json.GetInt("G"));
		g_rcColor[client][2] = view_as<int>(json.GetInt("B"));
		if (g_bLoaded[client])
		{
			PrintToChat(client, "\x01[\x0E%s\x01] 获取浮游炮设定\x06成功\x01, \x04到期时间\x01[\x0E%i\x01] \x04颜色\x01(\x0E%i\x01, \x0E%i\x01, \x0E%i\x01)", RCprefix, g_unix[client], g_rcColor[client][0], g_rcColor[client][1], g_rcColor[client][2]);
		}
		else
			PrintToChat(client, "\x01[\x0E%s\x01] 获取浮游炮设定\x04失败\x01, 您\x07未获得\x01浮游炮使用权", RCprefix);
	}
}

public void UpdateCallback(HTTPResponse response, int client)
{
	if (response.Status != HTTPStatus_OK)
	{
		if (IsClientInGame(client))
			PrintToChat(client, "\x01[\x0E%s\x01] 更改浮游炮RBG设定\x07失败 \x01(API未响应), \x04请稍后\x01重试。", RCprefix);
		return;
	}
	if (response.Data == null)
	{
		if (IsClientInGame(client))
			PrintToChat(client, "\x01[\x0E%s\x01] 更改浮游炮RBG设定\x07失败 \x01(返回值为空), \x04请稍后\x01重试。", RCprefix);
		return;
	}
	JSONObject json = view_as<JSONObject>(response.Data);
	if (IsValidClient(client))
	{
		if (!(view_as<bool>(json.GetBool("Status"))))
		{
			PrintToChat(client, "\x01[\x0E%s\x01] 更新浮游炮RBG设定\x07失败 \x01(API返回错误), \x04请稍后\x01重试。", RCprefix);
		}else{
			PrintToChat(client, "\x01[\x0E%s\x01] 更新浮游炮RBG设定\x04成功", RCprefix);
		}
	}
}

public void ExchangeCallback(HTTPResponse response, int client)
{
	if (response.Status != HTTPStatus_OK)
	{
		if (IsClientInGame(client))
			PrintToChat(client, "\x01[\x0E%s\x01] 兑换浮游炮CDKEY\x07失败 \x01(API未响应), \x04请稍后\x01重试。", RCprefix);
		return;
	}
	if (response.Data == null)
	{
		if (IsClientInGame(client))
			PrintToChat(client, "\x01[\x0E%s\x01] 兑换浮游炮CDKEY\x07失败 \x01(返回值为空), \x04请稍后\x01重试。", RCprefix);
		return;
	}
	JSONObject json = view_as<JSONObject>(response.Data);
	if (IsValidClient(client))
	{
		char msg[256];
		json.GetString("Msg", msg, sizeof msg);
		if (!(view_as<bool>(json.GetBool("status"))))
		{
			PrintToChat(client, "\x01[\x0E%s\x01] 兑换浮游炮CDKEY\x07失败 \x01(%s), \x04请稍后\x01重试。", RCprefix, msg);
		}else{
			PrintToChat(client, "\x01[\x0E%s\x01] 兑换浮游炮\x04成功\x01, 输入!rc查看。", RCprefix);
		}
	}
	delete json;
}



/*------------------------------------插件验证------------------------------------*/
public void PluginVerify()
{
	HTTPRequest Verify = new HTTPRequest(CloudServer);
	Verify.Get(VerifyReceived);
}
public void VerifyReceived(HTTPResponse response, any value)
{
	if (response.Status != HTTPStatus_OK)
	{
		PrintToServer("<!> CloudSever threw back an error # 1, so the \"Railcannon\" plugin cannot continue running...");
		SetFailState("<!> CloudSever threw back an error # 1, so the \"Railcannon\" plugin cannot continue running...");
	}
	if (response.Data == null)
	{
		PrintToServer("<!> CloudSever threw back an error # 2, so the \"Railcannon\" plugin cannot continue running...");
		SetFailState("<!> CloudSever threw back an error # 2, so the \"Railcannon\" plugin cannot continue running...");
	}
	JSONObject JsonObject = view_as<JSONObject>(response.Data);
	if (!(view_as<bool>(JsonObject.GetBool("Permission"))))
	{
		delete JsonObject;
		PrintToServer("<!> Local server does not have permission to use the plugin, so the \"Railcannon\" plugin cannot continue running...");
		SetFailState("<!> Local server does not have permission to use the plugin, so the \"Railcannon\" plugin cannot continue running...");
	}
}
/*------------------------------------插件验证------------------------------------*/