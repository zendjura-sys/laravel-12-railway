using System;
using System.Collections.Generic;
using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Главная: профиль и уровень, кликер, сводка, облако и рейтинг, новости, престиж, настройки
    public class HomeScreen : GameScreen
    {
        public HomeScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Главная"; } }

        Btn tap, clickUp, prestigeBtn, promoteBtn, shopBtn;
        TextView levelTitle, levelInfo, unlocks, stats, news, prestigeText;
        ProgressBar xpBar;
        TextView cloudText, boardText;
        Btn signIn, cloudSave, cloudLoad, signOut, boardBtn;
        List<LeaderRow> board;
        string boardError;

        protected override string Signature()
        {
            return UI.Cloud.Configured + "|" + UI.Cloud.SignedIn;
        }

        protected override void Build()
        {
            // --- Профиль и уровень ---
            var prof = Cell();
            var top = Ui.Row(prof, 0, 12);
            Ui.Badge(top, "🎖️", 50);
            var st = Ui.Stack(top);
            levelTitle = Ui.Label(st, "", 18, Pal.Text, true);
            levelInfo = Ui.Label(st, "", 13, Pal.Muted);
            xpBar = Ui.Progress(prof, Pal.Green);
            unlocks = Ui.Body(prof, 13);
            var prow = Ui.Row(prof, 52, 8);
            promoteBtn = Ui.Button(prow, "Повысить уровень", Pal.Gold, Promote, 15, 0);
            shopBtn = Ui.Button(prow, "В магазин", Pal.Blue, () => UI.SwitchTab(Shell.TabShop), 15, 0);

            // --- Кликер ---
            var hero = Cell();
            tap = Ui.Button(hero, "", Pal.Gold, null, 30, 150);
            tap.View.SetMaxLines(3);
            // pointer down — мгновенный отклик, можно тапать несколькими пальцами
            tap.View.Touch += (s, e) =>
            {
                var a = e.Event.ActionMasked;
                if (a == MotionEventActions.Down || a == MotionEventActions.PointerDown)
                {
                    int i = e.Event.ActionIndex;
                    var loc = new int[2];
                    tap.View.GetLocationInWindow(loc);
                    OnTap(loc[0] + e.Event.GetX(i), loc[1] + e.Event.GetY(i));
                }
                e.Handled = false;
            };
            clickUp = Ui.Button(hero, "", Pal.Blue, () => UI.Try(E.UpgradeClick(), "Недостаточно средств"), 15, 52);

            // --- Сводка ---
            var sum = Cell();
            Ui.Subtitle(sum, "Ваша империя");
            stats = Ui.Body(sum, 15);

            BuildCloud();

            // --- Новости ---
            var nc = Cell();
            Ui.Subtitle(nc, "Новости рынка");
            news = Ui.Body(nc, 14);

            // --- Престиж ---
            var pc = Cell();
            Ui.Subtitle(pc, "Новая жизнь");
            prestigeText = Ui.Body(pc, 14);
            prestigeBtn = Ui.Button(pc, "Начать заново с репутацией", Pal.Green, AskPrestige, 16);

            // --- Настройки ---
            var sc = Cell();
            Ui.Subtitle(sc, "Настройки");
            var row = Ui.Row(sc, 52, 10);
            Ui.Button(row, "Сохранить", Pal.Blue, () => { UI.Activity.Save(); UI.Toast("Игра сохранена"); }, 15, 0);
            Ui.Button(row, "Сбросить игру", Pal.Red, AskReset, 15, 0);
        }

        void BuildCloud()
        {
            var cc = Cell();
            Ui.Subtitle(cc, "☁️ Аккаунт и рейтинг");
            cloudText = Ui.Body(cc, 14);
            signIn = cloudSave = cloudLoad = signOut = boardBtn = null;
            boardText = null;
            if (!UI.Cloud.Configured) return;
            if (!UI.Cloud.SignedIn)
            {
                signIn = Ui.Button(cc, "G  Войти через Google", Pal.Blue, () => UI.Activity.SignIn(), 16, 54);
                return;
            }
            var row = Ui.Row(cc, 52, 8);
            cloudSave = Ui.Button(row, "Сохранить", Pal.Green, () => UI.Activity.CloudSave(true), 14, 0);
            cloudLoad = Ui.Button(row, "Загрузить", Pal.Blue, () => UI.Activity.CloudLoad(), 14, 0);
            signOut = Ui.Button(row, "Выйти", Pal.Btn, () => { UI.Activity.SignOut(); board = null; }, 14, 0);

            Ui.Label(cc, "🏆 Рейтинг магнатов", 16, Pal.Text, true);
            boardText = Ui.Body(cc, 14);
            boardBtn = Ui.Button(cc, "Обновить рейтинг", Pal.Btn, LoadBoard, 14, 46);
            if (board == null) LoadBoard();
        }

        async void LoadBoard()
        {
            if (!UI.Cloud.SignedIn) return;
            try
            {
                board = await UI.Cloud.Leaderboard();
                boardError = null;
            }
            catch (Exception e)
            {
                boardError = e.Message;
            }
        }

        void Promote()
        {
            var err = E.PromoteLevel();
            if (err != null) UI.Toast(err);
            UI.Refresh();
        }

        void OnTap(float x, float y)
        {
            double v = E.Click();
            tap.View.PerformHapticFeedback(FeedbackConstants.KeyboardTap);
            UI.FloatText(x, y, "+" + Fmt.Money(v));
        }

        void AskPrestige()
        {
            int g = E.RepGain;
            if (g < 1)
            {
                UI.Toast("Нужно заработать больше за эту жизнь");
                return;
            }
            UI.ShowModal("Новая жизнь",
                "Вы продадите всю империю и начнёте с нуля, но получите +" + g + " репутации.\n\n" +
                "Каждая единица репутации даёт +10% ко всему доходу навсегда. Уровень сохраняется.",
                "Начать", () => { E.Prestige(); UI.Activity.Save(); UI.SwitchTab(0); }, "Отмена");
        }

        void AskReset()
        {
            UI.ShowModal("Сброс", "Удалить весь прогресс, включая репутацию и уровень? Это действие нельзя отменить.",
                "Удалить", () => { UI.Activity.ResetGame(); UI.Toast("Прогресс сброшен"); }, "Отмена", Pal.Red);
        }

        // Что откроется на следующем уровне
        string NextUnlocks()
        {
            int next = S.level + 1;
            var list = new List<string>();
            foreach (var b in GameData.Businesses) if (b.ReqLevel == next) list.Add(b.Name);
            foreach (var i in GameData.Industries) if (i.ReqLevel == next) list.Add("отрасль «" + i.Name + "»");
            foreach (var f in GameData.Forms) if (f.ReqLevel == next) list.Add(f.Short);
            foreach (var it in GameData.Items) if (it.ReqLevel == next) list.Add(it.Name);
            if (next == GameData.LevelDeposit) list.Add("банковский вклад");
            if (next == GameData.LevelStocks) list.Add("торговля акциями");
            if (next == GameData.LevelLoans) list.Add("кредиты");
            if (next == GameData.LevelOptions) list.Add("опционы");
            if (next == GameData.HoldingLevel) list.Add("холдинг");
            return list.Count == 0 ? "" : "Уровень " + next + " откроет: " + string.Join(", ", list);
        }

        protected override void Sync()
        {
            // профиль
            Ui.Set(levelTitle, "Уровень " + S.level + (E.IsMaxLevel ? " (максимум)" : ""));
            var gate = E.LevelGate;
            if (E.IsMaxLevel) Ui.Set(levelInfo, "Вы достигли вершины бизнеса");
            else Ui.Set(levelInfo, "Опыт: " + Fmt.Num(S.xp) + " / " + Fmt.Num(E.XpToNext));
            xpBar.Progress = E.IsMaxLevel ? 1000 : (int)(Math.Min(1, S.xp / E.XpToNext) * 1000);
            var u = new Rich();
            if (gate != null)
            {
                var item = GameData.Items[gate[0]];
                bool have = S.items[gate[0]] >= gate[1];
                u.T("Для уровня " + (S.level + 1) + " нужно: ")
                 .C(IconOf(item.Id) + " " + item.Name + " × " + gate[1] + " (есть " + S.items[gate[0]] + ")", have ? Pal.Green : Pal.Gold);
                if (!E.LevelBlocked) u.N().T("Сначала наберите опыт до конца шкалы");
                u.N();
            }
            u.T(NextUnlocks());
            Ui.Set(unlocks, u);
            Ui.Show(promoteBtn.View.Parent as View, gate != null);
            promoteBtn.Enabled = E.LevelBlocked && gate != null && S.items[gate[0]] >= gate[1];

            // кликер
            tap.SetRich(new Rich().T("ЗАКЛЮЧИТЬ СДЕЛКУ\n").Small("+" + Fmt.Money(E.ClickValue), Pal.TextDark));
            clickUp.Text = "Навыки переговоров · ур. " + S.clickLvl + "\n" + Fmt.Money(E.ClickUpgradeCost);
            clickUp.Enabled = S.money >= E.ClickUpgradeCost;

            // сводка
            int units = 0;
            foreach (var b in S.biz) units += b.n;
            var r = new Rich();
            r.T("Доход: ").C(Fmt.Money(E.IncomePerSec) + "/сек", Ui.Sign(E.IncomePerSec)).N();
            r.T("Капитал: ").C(Fmt.Money(E.NetWorth()), Pal.Gold).N();
            r.T("Бизнес-точек: " + units + " · компаний: " + S.companies.Count).N();
            if (E.HasHolding) r.T("Холдинг: «" + S.holdingName + "»").N();
            r.T("Портфель акций: " + Fmt.Money(E.PortfolioValue()) + " · опционов: " + S.options.Count).N();
            r.T("В банке: " + Fmt.Money(S.bankDep));
            if (S.bankLoan > 0) r.T(" · долг ").C(Fmt.Money(S.bankLoan), Pal.Red);
            r.N().T("Всего заработано: " + Fmt.Money(S.totalEarned)).N();
            r.T("Сделок кликом: " + S.clicks + " · на бирже: " + S.trades);
            Ui.Set(stats, r);

            SyncCloud();

            // новости
            var n = new Rich();
            int shown = 0;
            foreach (var item in S.news)
            {
                if (shown >= 8) break;
                if (shown > 0) n.N();
                shown++;
                n.C("● ", item.kind > 0 ? Pal.Green : item.kind < 0 ? Pal.Red : Pal.Muted).T(item.text);
            }
            if (shown == 0) n.T("Пока тихо...");
            Ui.Set(news, n);

            int g = E.RepGain;
            var p = new Rich();
            p.T("Репутация: " + S.rep + " (+" + (S.rep * 10) + "% к доходу)").N();
            p.T("Заработано в этой жизни: " + Fmt.Money(S.runEarned)).N();
            if (g > 0) p.C("Доступно: +" + g + " репутации", Pal.Gold);
            else p.T("Первая репутация — после " + Fmt.Money(1e7) + " заработка");
            Ui.Set(prestigeText, p);
            prestigeBtn.Enabled = g > 0;
        }

        void SyncCloud()
        {
            var c = UI.Cloud;
            var r = new Rich();
            if (!c.Configured)
            {
                r.T("Облако не подключено. Добавьте файл google-services.json из Firebase (инструкция в README), " +
                    "чтобы войти через Google, сохранять прогресс и соревноваться в рейтинге.");
            }
            else if (!c.SignedIn)
            {
                r.T("Войдите, чтобы прогресс сохранялся в облаке и вы попали в рейтинг.");
                if (c.Status.Length > 0) r.N().C(c.Status, Pal.Gold);
            }
            else
            {
                r.T("Аккаунт: ").B(c.Name ?? "Игрок", Pal.Text);
                if (c.Email != null) r.T(" (" + c.Email + ")");
                r.N().T("Автосохранение в облако каждые 2 минуты");
                if (c.Status.Length > 0) r.N().C(c.Status, c.Status.StartsWith("Ошибка") ? Pal.Red : Pal.Green);
            }
            Ui.Set(cloudText, r);
            if (signIn != null) signIn.Enabled = !c.Busy;
            if (cloudSave != null) { cloudSave.Enabled = !c.Busy; cloudLoad.Enabled = !c.Busy; }

            if (boardText != null)
            {
                var b = new Rich();
                if (boardError != null) b.C("Не удалось загрузить: " + boardError, Pal.Red);
                else if (board == null) b.T("Загрузка...");
                else if (board.Count == 0) b.T("Пока пусто — станьте первым!");
                else
                {
                    for (int i = 0; i < board.Count; i++)
                    {
                        var row = board[i];
                        if (i > 0) b.N();
                        string medal = i == 0 ? "🥇" : i == 1 ? "🥈" : i == 2 ? "🥉" : (i + 1) + ".";
                        var line = medal + " " + row.Name + " · ур." + row.Level + " · " + Fmt.Money(row.NetWorth);
                        if (row.Me) b.B(line, Pal.Gold); else b.T(line);
                    }
                }
                Ui.Set(boardText, b);
            }
        }
    }
}
