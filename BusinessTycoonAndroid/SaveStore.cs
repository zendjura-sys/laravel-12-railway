using System;
using System.Text.Json;
using System.Text.Json.Serialization;
using Android.Content;

namespace Tycoon.Droid
{
    // JSON через source generator: работает и с обрезкой кода (trimming) в Release-сборке
    [JsonSourceGenerationOptions(IncludeFields = true,
        NumberHandling = JsonNumberHandling.AllowNamedFloatingPointLiterals)]
    [JsonSerializable(typeof(GameState))]
    internal partial class SaveJsonContext : JsonSerializerContext
    {
    }

    public static class SaveStore
    {
        const string Prefs = "tycoon";
        const string Key = "save_v1";

        public static string Serialize(GameState s) { return JsonSerializer.Serialize(s, SaveJsonContext.Default.GameState); }

        // Разбор сохранения (из облака); null — повреждено или неизвестная версия
        public static GameState Parse(string json)
        {
            try
            {
                var s = JsonSerializer.Deserialize(json, SaveJsonContext.Default.GameState);
                if (s == null || !s.IsSupported) return null;
                s.Normalize();
                return s;
            }
            catch (Exception) { return null; }
        }

        public static void Save(Context ctx, GameState s)
        {
            var json = Serialize(s);
            var prefs = ctx.GetSharedPreferences(Prefs, FileCreationMode.Private);
            var ed = prefs.Edit();
            ed.PutString(Key, json);
            ed.Apply();
        }

        public static GameState Load(Context ctx)
        {
            try
            {
                var prefs = ctx.GetSharedPreferences(Prefs, FileCreationMode.Private);
                var json = prefs.GetString(Key, null);
                if (string.IsNullOrEmpty(json)) return null;
                var s = JsonSerializer.Deserialize(json, SaveJsonContext.Default.GameState);
                if (s == null || !s.IsSupported) return null;
                s.Normalize();
                return s;
            }
            catch (Exception e)
            {
                Android.Util.Log.Warn("Tycoon", "Не удалось загрузить сохранение: " + e.Message);
                return null;
            }
        }

        public static void Clear(Context ctx)
        {
            var ed = ctx.GetSharedPreferences(Prefs, FileCreationMode.Private).Edit();
            ed.Remove(Key);
            ed.Apply();
        }
    }
}
