using System;
using Tycoon.UI;
using UnityEngine;

namespace Tycoon
{
    // Точка входа: создаётся автоматически после загрузки любой сцены,
    // поэтому проекту не нужны настроенные вручную сцены и префабы.
    public static class Bootstrap
    {
        [RuntimeInitializeOnLoadMethod(RuntimeInitializeLoadType.AfterSceneLoad)]
        static void Init()
        {
            if (GameController.Instance != null) return;
            var go = new GameObject("Game");
            UnityEngine.Object.DontDestroyOnLoad(go);
            go.AddComponent<GameController>();
        }
    }

    public class GameController : MonoBehaviour
    {
        public static GameController Instance { get; private set; }

        const string SaveKey = "tycoon_save_v1";
        const float TickStep = 0.1f;
        const float UiStep = 0.2f;
        const float SaveStep = 10f;

        public GameEngine Engine { get; private set; }
        UIRoot ui;
        float tickAcc, uiAcc, saveAcc;

        static long Now { get { return DateTimeOffset.UtcNow.ToUnixTimeSeconds(); } }

        void Awake()
        {
            Instance = this;
            Application.targetFrameRate = 60;
            Input.multiTouchEnabled = true;
            EnsureCamera();

            var state = LoadState();
            Engine = new GameEngine(state);
            ui = new UIRoot(this);
            ui.Build();

            if (state != null) CatchUp(Now - state.lastSeenUnix);
            else Engine.AddNews("Добро пожаловать! Кликайте, покупайте первую ферму и стройте империю.", 1);
            Engine.S.lastSeenUnix = Now;
            ui.Refresh();
        }

        static void EnsureCamera()
        {
            if (Camera.main != null) return;
            var cam = new GameObject("Main Camera").AddComponent<Camera>();
            cam.tag = "MainCamera";
            cam.clearFlags = CameraClearFlags.SolidColor;
            cam.backgroundColor = Pal.Bg;
            cam.orthographic = true;
            DontDestroyOnLoad(cam.gameObject);
        }

        void Update()
        {
            float dt = Mathf.Min(Time.unscaledDeltaTime, 1f);

            tickAcc += dt;
            while (tickAcc >= TickStep)
            {
                tickAcc -= TickStep;
                Engine.Tick(TickStep);
            }

            uiAcc += dt;
            if (uiAcc >= UiStep)
            {
                uiAcc = 0;
                ui.Refresh();
            }

            saveAcc += dt;
            if (saveAcc >= SaveStep)
            {
                saveAcc = 0;
                Save();
            }

            // Кнопка «Назад» на Android
            if (Input.GetKeyDown(KeyCode.Escape)) ui.OnBack();
        }

        // Возврат из фона: досчитываем прогресс за время отсутствия
        void OnApplicationPause(bool paused)
        {
            if (paused) { Save(); return; }
            CatchUp(Now - Engine.S.lastSeenUnix);
            Engine.S.lastSeenUnix = Now;
        }

        void OnApplicationQuit() { Save(); }

        void CatchUp(long seconds)
        {
            if (seconds < 10) return;
            var rep = Engine.SimulateOffline(seconds);
            if (rep.Money > 0 || seconds > 60) ui.ShowOfflineReport(rep);
        }

        public void Save()
        {
            if (Engine == null) return;
            Engine.S.lastSeenUnix = Now;
            PlayerPrefs.SetString(SaveKey, JsonUtility.ToJson(Engine.S));
            PlayerPrefs.Save();
        }

        static GameState LoadState()
        {
            if (!PlayerPrefs.HasKey(SaveKey)) return null;
            try
            {
                var s = JsonUtility.FromJson<GameState>(PlayerPrefs.GetString(SaveKey));
                if (s == null || s.v != 1) return null;
                s.Normalize();
                return s;
            }
            catch (Exception e)
            {
                Debug.LogWarning("Не удалось загрузить сохранение: " + e.Message);
                return null;
            }
        }

        public void ResetGame()
        {
            PlayerPrefs.DeleteKey(SaveKey);
            Engine.S = GameState.Create();
            Engine.IncomePerSec = 0;
            Engine.S.lastSeenUnix = Now;
            Engine.StructureDirty = true;
            Save();
        }

        // Сворачивает приложение вместо выхода (как принято на Android)
        public static void MinimizeApp()
        {
#if UNITY_ANDROID && !UNITY_EDITOR
            try
            {
                using (var player = new AndroidJavaClass("com.unity3d.player.UnityPlayer"))
                using (var activity = player.GetStatic<AndroidJavaObject>("currentActivity"))
                {
                    activity.Call<bool>("moveTaskToBack", true);
                }
            }
            catch (Exception)
            {
                Application.Quit();
            }
#else
            Application.Quit();
#endif
        }
    }
}
