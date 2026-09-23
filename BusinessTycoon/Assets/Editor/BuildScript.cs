using System;
using System.IO;
using System.Linq;
using UnityEditor;
using UnityEditor.Build.Reporting;
using UnityEditor.SceneManagement;
using UnityEngine;

// Сборка без ручной настройки редактора: создаёт сцену, выставляет Player Settings и собирает APK.
// В редакторе: меню Tycoon → Build Android APK. В CI: -executeMethod BuildScript.BuildAndroidCI
public static class BuildScript
{
    const string ScenePath = "Assets/Scenes/Main.unity";
    const string DefaultApk = "Builds/Android/BusinessTycoon.apk";

    [MenuItem("Tycoon/Setup Project")]
    public static void Setup()
    {
        EnsureScene();
        ConfigurePlayer();
        AssetDatabase.SaveAssets();
        Debug.Log("Tycoon: проект настроен");
    }

    static void EnsureScene()
    {
        if (!File.Exists(ScenePath))
        {
            Directory.CreateDirectory(Path.GetDirectoryName(ScenePath));
            // Пустая сцена: камеру и весь интерфейс создаёт Bootstrap при запуске
            var scene = EditorSceneManager.NewScene(NewSceneSetup.EmptyScene, NewSceneMode.Single);
            EditorSceneManager.SaveScene(scene, ScenePath);
        }
        EditorBuildSettings.scenes = new[] { new EditorBuildSettingsScene(ScenePath, true) };
    }

    static void ConfigurePlayer()
    {
        PlayerSettings.companyName = "Tycoon Games";
        PlayerSettings.productName = "Бизнес Магнат";
        PlayerSettings.bundleVersion = "1.0.0";
        PlayerSettings.Android.bundleVersionCode = 1;
        PlayerSettings.SetApplicationIdentifier(BuildTargetGroup.Android, "com.tycoongames.bizmagnat");
        PlayerSettings.defaultInterfaceOrientation = UIOrientation.Portrait;
        PlayerSettings.Android.minSdkVersion = AndroidSdkVersions.AndroidApiLevel23;
        PlayerSettings.SetScriptingBackend(BuildTargetGroup.Android, ScriptingImplementation.IL2CPP);
        PlayerSettings.Android.targetArchitectures = AndroidArchitecture.ARMv7 | AndroidArchitecture.ARM64;
        PlayerSettings.SplashScreen.showUnityLogo = false;
        PlayerSettings.runInBackground = false;
    }

    [MenuItem("Tycoon/Build Android APK")]
    public static void BuildAndroid() { Build(DefaultApk); }

    // Для GitHub Actions (game-ci): путь берём из -customBuildPath, ошибка → код выхода 1
    public static void BuildAndroidCI()
    {
        var args = Environment.GetCommandLineArgs();
        string path = DefaultApk;
        for (int i = 0; i < args.Length - 1; i++)
            if (args[i] == "-customBuildPath") path = args[i + 1];
        if (!path.EndsWith(".apk")) path = Path.Combine(path, "BusinessTycoon.apk");

        bool ok = Build(path);
        EditorApplication.Exit(ok ? 0 : 1);
    }

    static bool Build(string path)
    {
        Setup();
        EditorUserBuildSettings.buildAppBundle = false;
        Directory.CreateDirectory(Path.GetDirectoryName(path));
        var opts = new BuildPlayerOptions
        {
            scenes = EditorBuildSettings.scenes.Where(s => s.enabled).Select(s => s.path).ToArray(),
            locationPathName = path,
            target = BuildTarget.Android,
            options = BuildOptions.None,
        };
        var report = BuildPipeline.BuildPlayer(opts);
        var ok = report.summary.result == BuildResult.Succeeded;
        Debug.Log("Tycoon: сборка " + (ok ? "успешна → " + path : "провалилась: " + report.summary.result));
        return ok;
    }
}
