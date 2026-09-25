using System;
using System.Collections.Generic;
using System.IO;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Text.Json.Nodes;
using System.Threading.Tasks;
using Android.App;
using Android.Content;
using AndroidX.Credentials;
using Xamarin.GoogleAndroid.Libraries.Identity.GoogleId;

// Firebase без нативного SDK:
//   вход — Google (Credential Manager) → Firebase Auth REST (signInWithIdp);
//   данные — Realtime Database REST: /bizmagnat/users/{uid}/save и /bizmagnat/leaderboard/{uid}.
// Настройки берутся из google-services.json, который кладётся в папку проекта.
namespace Tycoon.Droid
{
    public class FirebaseConfig
    {
        public string ApiKey, ProjectId, DbUrl, WebClientId;

        public bool IsValid { get { return ApiKey != null && DbUrl != null && WebClientId != null; } }

        public static FirebaseConfig Load(Context ctx)
        {
            try
            {
                string json;
                using (var s = ctx.Assets.Open("google-services.json"))
                using (var r = new StreamReader(s)) json = r.ReadToEnd();
                var root = JsonNode.Parse(json);
                var cfg = new FirebaseConfig();
                var info = root["project_info"];
                cfg.ProjectId = (string)info?["project_id"];
                cfg.DbUrl = (string)info?["firebase_url"];
                if (cfg.DbUrl == null && cfg.ProjectId != null) cfg.DbUrl = "https://" + cfg.ProjectId + "-default-rtdb.firebaseio.com";

                // клиент для нашего пакета (или первый)
                JsonNode client = null;
                foreach (var c in root["client"].AsArray())
                {
                    var pkg = (string)c?["client_info"]?["android_client_info"]?["package_name"];
                    if (client == null || pkg == ctx.PackageName) client = c;
                    if (pkg == ctx.PackageName) break;
                }
                cfg.ApiKey = (string)client?["api_key"]?[0]?["current_key"];
                cfg.WebClientId = FindWebClient(client?["oauth_client"]) ??
                                  FindWebClient(client?["services"]?["appinvite_service"]?["other_platform_oauth_client"]);
                return cfg;
            }
            catch (Exception e)
            {
                Android.Util.Log.Info("Tycoon", "Firebase не настроен: " + e.Message);
                return null;
            }
        }

        // client_type 3 — веб-клиент OAuth, его ID нужен для входа через Google
        static string FindWebClient(JsonNode list)
        {
            if (list == null) return null;
            foreach (var o in list.AsArray())
                if ((int?)o?["client_type"] == 3) return (string)o["client_id"];
            return null;
        }
    }

    public class LeaderRow
    {
        public string Name;
        public double NetWorth;
        public int Level;
        public bool Me;
    }

    public class CloudService
    {
        const string Prefs = "tycoon_cloud";
        readonly Context ctx;
        readonly HttpClient http = new HttpClient { Timeout = TimeSpan.FromSeconds(20) };

        public readonly FirebaseConfig Config;
        public string Uid { get; private set; }
        public string Name { get; private set; }
        public string Email { get; private set; }
        public string Status = "";
        public DateTime LastSync = DateTime.MinValue;
        public bool Busy;

        string idToken, refreshToken;
        DateTime expiresAt = DateTime.MinValue;

        public CloudService(Context ctx)
        {
            this.ctx = ctx;
            Config = FirebaseConfig.Load(ctx);
            var p = ctx.GetSharedPreferences(Prefs, FileCreationMode.Private);
            Uid = p.GetString("uid", null);
            Name = p.GetString("name", null);
            Email = p.GetString("email", null);
            refreshToken = p.GetString("refresh", null);
        }

        public bool Configured { get { return Config != null && Config.IsValid; } }
        public bool SignedIn { get { return Uid != null && refreshToken != null; } }

        void SaveSession()
        {
            var e = ctx.GetSharedPreferences(Prefs, FileCreationMode.Private).Edit();
            e.PutString("uid", Uid);
            e.PutString("name", Name);
            e.PutString("email", Email);
            e.PutString("refresh", refreshToken);
            e.Apply();
        }

        public void SignOut()
        {
            Uid = Name = Email = idToken = refreshToken = null;
            var e = ctx.GetSharedPreferences(Prefs, FileCreationMode.Private).Edit();
            e.Clear();
            e.Apply();
            Status = "Вы вышли из аккаунта";
        }

        // ---------- Вход через Google ----------
        public Task<string> GoogleIdToken(Activity activity)
        {
            var tcs = new TaskCompletionSource<string>();
            var option = new GetSignInWithGoogleOption.Builder(Config.WebClientId).Build();
            var request = new GetCredentialRequest.Builder().AddCredentialOption(option).Build();
            var cm = CredentialManager.Companion.Create(activity);
            cm.GetCredentialAsync(activity, request, new Android.OS.CancellationSignal(), activity.MainExecutor,
                new CredentialCallback(tcs));
            return tcs.Task;
        }

        class CredentialCallback : Java.Lang.Object, ICredentialManagerCallback
        {
            readonly TaskCompletionSource<string> tcs;
            public CredentialCallback(TaskCompletionSource<string> t) { tcs = t; }

            public void OnResult(Java.Lang.Object result)
            {
                var cred = (result as GetCredentialResponse)?.Credential as CustomCredential;
                if (cred != null && cred.Type == GoogleIdTokenCredential.TypeGoogleIdTokenCredential)
                    tcs.TrySetResult(GoogleIdTokenCredential.CreateFrom(cred.Data).IdToken);
                else
                    tcs.TrySetException(new Exception("Неожиданный тип учётных данных"));
            }

            public void OnError(Java.Lang.Object e)
            {
                var text = e == null ? "неизвестная ошибка" : e.ToString();
                tcs.TrySetException(new Exception(text.Contains("Cancellation") ? "Вход отменён" : text));
            }
        }

        public async Task SignIn(Activity activity)
        {
            var googleToken = await GoogleIdToken(activity);
            var body = new JsonObject
            {
                ["postBody"] = "id_token=" + googleToken + "&providerId=google.com",
                ["requestUri"] = "http://localhost",
                ["returnIdpCredential"] = true,
                ["returnSecureToken"] = true,
            };
            var resp = await PostJson("https://identitytoolkit.googleapis.com/v1/accounts:signInWithIdp?key=" + Config.ApiKey, body);
            idToken = (string)resp["idToken"];
            refreshToken = (string)resp["refreshToken"];
            Uid = (string)resp["localId"];
            Name = (string)resp["displayName"] ?? (string)resp["fullName"] ?? "Игрок";
            Email = (string)resp["email"];
            expiresAt = DateTime.UtcNow.AddSeconds(double.Parse((string)resp["expiresIn"] ?? "3600") - 60);
            SaveSession();
            Status = "Вы вошли как " + Name;
        }

        async Task<string> Token()
        {
            if (idToken != null && DateTime.UtcNow < expiresAt) return idToken;
            if (refreshToken == null) throw new Exception("Нужно войти через Google");
            var form = new FormUrlEncodedContent(new Dictionary<string, string>
            {
                { "grant_type", "refresh_token" },
                { "refresh_token", refreshToken },
            });
            var r = await http.PostAsync("https://securetoken.googleapis.com/v1/token?key=" + Config.ApiKey, form);
            var text = await r.Content.ReadAsStringAsync();
            if (!r.IsSuccessStatusCode) throw new Exception(ErrorOf(text, r));
            var j = JsonNode.Parse(text);
            idToken = (string)j["id_token"];
            refreshToken = (string)j["refresh_token"];
            expiresAt = DateTime.UtcNow.AddSeconds(double.Parse((string)j["expires_in"] ?? "3600") - 60);
            SaveSession();
            return idToken;
        }

        // ---------- Realtime Database ----------
        // Все данные игры лежат в отдельной ветке: база может быть общей с другими приложениями проекта
        const string Root = "bizmagnat/";

        string Db(string path, string token, string query = "")
        {
            return Config.DbUrl.TrimEnd('/') + "/" + Root + path + ".json?auth=" + Uri.EscapeDataString(token) + query;
        }

        // В общем рейтинге показываем только имя, без фамилии
        public string PublicName
        {
            get
            {
                var n = (Name ?? "").Trim();
                if (n.Length == 0) return "Игрок";
                int sp = n.IndexOf(' ');
                return sp > 0 ? n.Substring(0, sp) : n;
            }
        }

        public async Task Upload(string saveJson, double netWorth, int level)
        {
            var token = await Token();
            await Put(Db("users/" + Uid + "/save", token), saveJson);
            var row = new JsonObject
            {
                ["name"] = PublicName,
                ["netWorth"] = double.IsFinite(netWorth) ? Math.Floor(netWorth) : 0,
                ["level"] = level,
                ["updated"] = new JsonObject { [".sv"] = "timestamp" },
            };
            await Put(Db("leaderboard/" + Uid, token), row.ToJsonString());
            LastSync = DateTime.Now;
        }

        // null — сохранения в облаке нет
        public async Task<string> Download()
        {
            var token = await Token();
            var r = await http.GetAsync(Db("users/" + Uid + "/save", token));
            var text = await r.Content.ReadAsStringAsync();
            if (!r.IsSuccessStatusCode) throw new Exception(ErrorOf(text, r));
            return text == "null" ? null : text;
        }

        public async Task<List<LeaderRow>> Leaderboard(int count = 20)
        {
            var token = await Token();
            var q = "&orderBy=" + Uri.EscapeDataString("\"netWorth\"") + "&limitToLast=" + count;
            var r = await http.GetAsync(Db("leaderboard", token, q));
            var text = await r.Content.ReadAsStringAsync();
            if (!r.IsSuccessStatusCode) throw new Exception(ErrorOf(text, r));
            var rows = new List<LeaderRow>();
            var obj = JsonNode.Parse(text) as JsonObject;
            if (obj == null) return rows;
            foreach (var kv in obj)
            {
                rows.Add(new LeaderRow
                {
                    Name = (string)kv.Value?["name"] ?? "Игрок",
                    NetWorth = (double?)kv.Value?["netWorth"] ?? 0,
                    Level = (int?)kv.Value?["level"] ?? 1,
                    Me = kv.Key == Uid,
                });
            }
            rows.Sort((a, b) => b.NetWorth.CompareTo(a.NetWorth));
            return rows;
        }

        async Task Put(string url, string json)
        {
            var r = await http.PutAsync(url, new StringContent(json, Encoding.UTF8, "application/json"));
            if (!r.IsSuccessStatusCode) throw new Exception(ErrorOf(await r.Content.ReadAsStringAsync(), r));
        }

        async Task<JsonNode> PostJson(string url, JsonObject body)
        {
            var r = await http.PostAsync(url, new StringContent(body.ToJsonString(), Encoding.UTF8, "application/json"));
            var text = await r.Content.ReadAsStringAsync();
            if (!r.IsSuccessStatusCode) throw new Exception(ErrorOf(text, r));
            return JsonNode.Parse(text);
        }

        static string ErrorOf(string body, HttpResponseMessage r)
        {
            try
            {
                var j = JsonNode.Parse(body);
                var msg = (string)j?["error"]?["message"] ?? (string)j?["error"] ?? (string)j?["error_description"];
                if (msg != null) return msg;
            }
            catch (JsonException) { }
            return "HTTP " + (int)r.StatusCode;
        }
    }
}
