using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Главная: кнопка-кликер, навыки, сводка, новости, престиж, настройки
    public class HomeScreen : GameScreen
    {
        public HomeScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Главная"; } }

        Btn tap, clickUp, prestigeBtn;
        TextView stats, news, prestigeText;

        protected override void Build()
        {
            // --- Кликер ---
            var hero = Cell();
            tap = Ui.Button(hero, "", Pal.Gold, null, 30, 170);
            tap.View.SetMaxLines(3);
            tap.View.HapticFeedbackEnabled = true;
            // pointer down — мгновенный отклик и можно тапать несколькими пальцами
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
                "Каждая единица репутации даёт +10% ко всему доходу навсегда.",
                "Начать", () => { E.Prestige(); UI.Activity.Save(); UI.SwitchTab(0); }, "Отмена");
        }

        void AskReset()
        {
            UI.ShowModal("Сброс", "Удалить весь прогресс, включая репутацию? Это действие нельзя отменить.",
                "Удалить", () => { UI.Activity.ResetGame(); UI.Toast("Прогресс сброшен"); }, "Отмена", Pal.Red);
        }

        protected override void Sync()
        {
            tap.SetRich(new Rich().T("ЗАКЛЮЧИТЬ СДЕЛКУ\n").Small("+" + Fmt.Money(E.ClickValue), Pal.TextDark));
            clickUp.Text = "Навыки переговоров · ур. " + S.clickLvl + "\n" + Fmt.Money(E.ClickUpgradeCost);
            clickUp.Enabled = S.money >= E.ClickUpgradeCost;

            int units = 0;
            foreach (var b in S.biz) units += b.n;
            var r = new Rich();
            r.T("Доход: ").C(Fmt.Money(E.IncomePerSec) + "/сек", Pal.Green).N();
            r.T("Капитал: ").C(Fmt.Money(E.NetWorth()), Pal.Gold).N();
            r.T("Бизнес-точек: " + units).N();
            r.T("Портфель акций: " + Fmt.Money(E.PortfolioValue())).N();
            r.T("Опционов открыто: " + S.options.Count).N();
            r.T("Своих компаний: " + S.companies.Count).N();
            r.T("В банке: " + Fmt.Money(S.bankDep));
            if (S.bankLoan > 0) r.T(" · долг ").C(Fmt.Money(S.bankLoan), Pal.Red);
            r.N().T("Всего заработано: " + Fmt.Money(S.totalEarned)).N();
            r.T("Сделок кликом: " + S.clicks);
            Ui.Set(stats, r);

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
    }
}
