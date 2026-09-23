using System.Text;
using UnityEngine;
using UnityEngine.EventSystems;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Главная: кнопка-кликер, навыки, сводка, новости, престиж, настройки
    public class HomeScreen : GameScreen
    {
        public HomeScreen(UIRoot ui) : base(ui) { }
        public override string Title { get { return "Главная"; } }

        UIKit.Btn tap, clickUp, prestigeBtn;
        Text tapValue, stats, news, prestigeText;
        Pulse pulse;

        protected override void Build()
        {
            // --- Кликер ---
            var hero = Cell();
            tap = UIKit.Button(hero, "", Pal.Gold, null, 56, 380);
            tap.Label.text = "ЗАКЛЮЧИТЬ\nСДЕЛКУ";
            tap.Label.resizeTextMaxSize = 64;
            UIKit.Stretch(tap.Label.rectTransform, 12, 110, 12, 40);
            tapValue = UIKit.Label(tap.Face.transform, "", 44, Pal.TextDark, TextAnchor.LowerCenter, FontStyle.Bold);
            UIKit.Stretch(tapValue.rectTransform, 12, 40, 12, 40);
            pulse = tap.Lip.gameObject.AddComponent<Pulse>();
            // нажатие по pointer down — быстрее и позволяет тапать несколькими пальцами
            var trigger = tap.Lip.gameObject.AddComponent<EventTrigger>();
            var entry = new EventTrigger.Entry { eventID = EventTriggerType.PointerDown };
            entry.callback.AddListener(OnTap);
            trigger.triggers.Add(entry);

            clickUp = UIKit.Button(hero, "", Pal.Blue, () => UI.Try(E.UpgradeClick(), "Недостаточно средств"), 32, 110);

            // --- Сводка ---
            var sum = Cell();
            Subtitle(sum, "Ваша империя");
            stats = Body(sum, "", 32);
            stats.lineSpacing = 1.15f;

            // --- Новости ---
            var nc = Cell();
            Subtitle(nc, "Новости рынка");
            news = Body(nc, "", 30);
            news.lineSpacing = 1.1f;

            // --- Престиж ---
            var pc = Cell();
            Subtitle(pc, "Новая жизнь");
            prestigeText = Body(pc, "", 30);
            prestigeBtn = UIKit.Button(pc, "Начать заново с репутацией", Pal.Green, AskPrestige, 34);

            // --- Настройки ---
            var sc = Cell();
            Subtitle(sc, "Настройки");
            var row = UIKit.Row(sc, 110, 20);
            UIKit.Button(row, "Сохранить", Pal.Blue, () => { UI.Controller.Save(); UI.Toast("Игра сохранена"); }, 32);
            UIKit.Button(row, "Сбросить игру", Pal.Red, AskReset, 32);
        }

        void OnTap(BaseEventData data)
        {
            double v = E.Click();
            pulse.Kick();
            var pd = data as PointerEventData;
            Vector2 local;
            var parent = (RectTransform)Content.parent;
            if (pd == null || !RectTransformUtility.ScreenPointToLocalPointInRectangle(parent, pd.position, pd.pressEventCamera, out local))
                local = Vector2.zero;
            local += new Vector2(Random.Range(-40f, 40f), 40);
            FloatText.Spawn(parent, local, "+" + Fmt.Money(v), Pal.Gold);
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
                "Начать", () => { E.Prestige(); UI.Controller.Save(); UI.SwitchTab(0); }, "Отмена");
        }

        void AskReset()
        {
            UI.ShowModal("Сброс", "Удалить весь прогресс, включая репутацию? Это действие нельзя отменить.",
                "Удалить", () => { UI.Controller.ResetGame(); UI.Toast("Прогресс сброшен"); }, "Отмена", Pal.Red);
        }

        protected override void Sync()
        {
            tapValue.text = "+" + Fmt.Money(E.ClickValue);
            clickUp.Text = "Навыки переговоров · ур. " + S.clickLvl + "   " + Fmt.Money(E.ClickUpgradeCost);
            clickUp.Interactable = S.money >= E.ClickUpgradeCost;

            int units = 0;
            foreach (var b in S.biz) units += b.n;
            var sb = new StringBuilder();
            sb.Append("Доход: ").Append(UIKit.Col(Fmt.Money(E.IncomePerSec) + "/сек", Pal.Green)).Append('\n');
            sb.Append("Капитал: ").Append(UIKit.Col(Fmt.Money(E.NetWorth()), Pal.Gold)).Append('\n');
            sb.Append("Бизнес-точек: ").Append(units).Append('\n');
            sb.Append("Портфель акций: ").Append(Fmt.Money(E.PortfolioValue())).Append('\n');
            sb.Append("Опционов открыто: ").Append(S.options.Count).Append('\n');
            sb.Append("Своих компаний: ").Append(S.companies.Count).Append('\n');
            sb.Append("В банке: ").Append(Fmt.Money(S.bankDep));
            if (S.bankLoan > 0) sb.Append(" · долг ").Append(UIKit.Col(Fmt.Money(S.bankLoan), Pal.Red));
            sb.Append('\n');
            sb.Append("Всего заработано: ").Append(Fmt.Money(S.totalEarned)).Append('\n');
            sb.Append("Сделок кликом: ").Append(S.clicks);
            UIKit.SetText(stats, sb.ToString());

            sb.Length = 0;
            int shown = 0;
            foreach (var n in S.news)
            {
                if (shown++ >= 8) break;
                var c = n.kind > 0 ? Pal.Green : n.kind < 0 ? Pal.Red : Pal.Muted;
                sb.Append(UIKit.Col("• ", c)).Append(n.text).Append('\n');
            }
            UIKit.SetText(news, shown == 0 ? "Пока тихо..." : sb.ToString().TrimEnd());

            int g = E.RepGain;
            UIKit.SetText(prestigeText,
                "Репутация: " + S.rep + " (+" + (S.rep * 10) + "% к доходу)\n" +
                "Заработано в этой жизни: " + Fmt.Money(S.runEarned) + "\n" +
                (g > 0 ? UIKit.Col("Доступно: +" + g + " репутации", Pal.Gold)
                       : "Первая репутация — после " + Fmt.Money(1e7) + " заработка"));
            prestigeBtn.Interactable = g > 0;
        }
    }
}
