using System.Collections.Generic;
using System.Text;
using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Собственные компании: основание, найм, маркетинг, R&D, IPO, продажа
    public class CompanyScreen : GameScreen
    {
        public CompanyScreen(UIRoot ui) : base(ui) { }
        public override string Title { get { return "Мои компании"; } }

        string draftName = "";
        int draftInd;

        InputField nameField;
        UIKit.Btn[] indBtns;
        UIKit.Btn foundBtn;
        Text foundInfo;

        class Item
        {
            public int Id;
            public Text Title, Stats;
            public UIKit.Btn Hire, Mkt, Rnd, Sell;
            public UIKit.Btn[] Ipo;
            public Text IpoInfo;
        }

        readonly List<Item> items = new List<Item>();

        protected override string Signature()
        {
            var sb = new StringBuilder();
            sb.Append(E.CanFoundCompany).Append('|');
            foreach (var c in S.companies) sb.Append(c.id).Append(c.pub ? 'p' : 'c').Append(',');
            return sb.ToString();
        }

        protected override void Build()
        {
            items.Clear();
            if (E.CanFoundCompany) BuildFound();
            else Body(Content, "Вы основали максимум компаний (" + GameData.CompanyFoundCost.Length + ").", 30);
            foreach (var c in S.companies) items.Add(BuildCompany(c));
        }

        void BuildFound()
        {
            var card = Cell();
            Subtitle(card, "Основать компанию");
            var hint = Body(card, "Своя компания приносит выручку. Растите её, выводите на биржу (IPO) " +
                                  "и получайте деньги за продажу доли. Доход вам — пропорционально вашей доле.", 26);
            UIKit.LE(hint, -1);
            nameField = UIKit.Input(card, "Название компании");
            nameField.text = draftName;
            nameField.onValueChanged.AddListener(v => draftName = v);

            var row = UIKit.Row(card, 96, 10);
            indBtns = new UIKit.Btn[GameData.Industries.Length];
            for (int i = 0; i < indBtns.Length; i++)
            {
                int k = i;
                indBtns[i] = UIKit.Button(row, GameData.Industries[i].Name, Pal.Btn, () => { draftInd = k; Sync(); }, 24, 0);
            }
            foundInfo = Body(card, "", 28);
            foundBtn = UIKit.Button(card, "", Pal.Gold, Found, 34, 120);
        }

        void Found()
        {
            var err = E.FoundCompany(draftName, draftInd);
            if (err != null) { UI.Toast(err); return; }
            draftName = "";
            UI.Toast("Компания основана!");
            UI.Refresh();
        }

        Item BuildCompany(Company c)
        {
            var it = new Item { Id = c.id };
            var ind = GameData.Industries[c.ind];
            var card = Cell();

            var top = UIKit.Row(card, 100, 20);
            UIKit.Badge(top, c.name.Substring(0, 1).ToUpperInvariant(), ind.Color, 96);
            var stack = VStack(top);
            it.Title = UIKit.Label(stack, "", 36, Pal.Text, TextAnchor.MiddleLeft, FontStyle.Bold);
            UIKit.Label(stack, ind.Name, 26, Pal.Muted, TextAnchor.MiddleLeft);

            it.Stats = Body(card, "", 30);
            it.Stats.lineSpacing = 1.1f;

            it.Hire = UIKit.Button(card, "", Pal.Gold, () => UI.Try(E.CoHire(E.FindCompany(it.Id)), "Недостаточно средств"), 30, 110);
            var row = UIKit.Row(card, 120, 16);
            it.Mkt = UIKit.Button(row, "", Pal.Blue, () => UI.Try(E.CoMarketing(E.FindCompany(it.Id)), "Недостаточно средств"), 26, 0);
            it.Rnd = UIKit.Button(row, "", Pal.Blue, () => UI.Try(E.CoRnd(E.FindCompany(it.Id)), "Не хватает денег или сырья"), 24, 0);

            it.IpoInfo = Body(card, "", 28);
            if (!c.pub)
            {
                var irow = UIKit.Row(card, 110, 12);
                it.Ipo = new UIKit.Btn[GameData.IpoPercents.Length];
                for (int i = 0; i < it.Ipo.Length; i++)
                {
                    double pct = GameData.IpoPercents[i];
                    it.Ipo[i] = UIKit.Button(irow, "", Pal.Green, () => AskIpo(it.Id, pct), 26, 0);
                }
                it.Sell = UIKit.Button(card, "", Pal.Red, () => AskSell(it.Id), 28, 96);
            }
            else
            {
                UIKit.Button(card, "Открыть на бирже", Pal.Blue, () => UI.SwitchTab(3), 30, 100);
            }
            return it;
        }

        void AskIpo(int id, double pct)
        {
            var c = E.FindCompany(id);
            if (c == null) return;
            double val = E.CoValuation(c);
            UI.ShowModal("IPO",
                "Вывести «" + c.name + "» на биржу и продать " + Fmt.Pct(pct, false) + " акций инвесторам?\n\n" +
                "Вы получите около " + Fmt.Money(val * pct * 0.93) + " (7% — комиссия андеррайтера).\n" +
                "Ваш доход от компании станет пропорционален вашей доле. Акции можно докупать и продавать на бирже.",
                "Провести IPO", () => UI.Try(E.Ipo(E.FindCompany(id), pct), "IPO недоступно"), "Отмена");
        }

        void AskSell(int id)
        {
            var c = E.FindCompany(id);
            if (c == null) return;
            UI.ShowModal("Продажа компании",
                "Продать «" + c.name + "» стратегическому инвестору за " + Fmt.Money(E.CoValuation(c) * 0.7) + "?",
                "Продать", () => UI.Try(E.SellCompany(E.FindCompany(id)), "Нельзя продать"), "Отмена", Pal.Red);
        }

        protected override void Sync()
        {
            if (foundBtn != null && E.CanFoundCompany)
            {
                for (int i = 0; i < indBtns.Length; i++) indBtns[i].SetColor(i == draftInd ? Pal.Green : Pal.Btn);
                var ind = GameData.Industries[draftInd];
                UIKit.SetText(foundInfo, "Отрасль: " + ind.Name + " · волатильность акций " + Fmt.Pct(ind.Vol * 60, false) + "/мин");
                foundBtn.Text = "Основать за " + Fmt.Money(E.FoundCost);
                foundBtn.Interactable = S.money >= E.FoundCost;
            }

            foreach (var it in items)
            {
                var c = E.FindCompany(it.Id);
                if (c == null) continue;
                double rev = E.CoRevenue(c), frac = E.CoOwnFrac(c), val = E.CoValuation(c);
                UIKit.SetText(it.Title, c.name + (c.pub ? "  ·  " + c.ticker : "  ·  частная"));
                UIKit.SetText(it.Stats,
                    "Сотрудники: " + c.staff + " · Маркетинг: ур." + c.mkt + " · R&D: ур." + c.rnd + "\n" +
                    "Выручка: " + Fmt.Money(rev) + "/сек · ваша доля " + Fmt.Pct(frac, false) + "\n" +
                    "Ваш доход: " + UIKit.Col(Fmt.Money(rev * frac) + "/сек", Pal.Green) + "\n" +
                    "Оценка: " + UIKit.Col(Fmt.Money(val), Pal.Gold));

                double hc = E.CoHireCost(c), mc = E.CoMarketingCost(c);
                var rc = E.CoRndCost(c);
                it.Hire.Text = "Нанять сотрудника  " + Fmt.Money(hc);
                it.Hire.Interactable = S.money >= hc;
                it.Mkt.Text = "Маркетинг +30%\n" + Fmt.Money(mc);
                it.Mkt.Interactable = S.money >= mc;
                it.Rnd.Text = "R&D ×2\n" + CostText(rc);
                it.Rnd.Interactable = E.CanPay(rc);

                if (c.pub)
                {
                    var st = E.Stock(c.ticker);
                    UIKit.SetText(it.IpoInfo, "Акции на бирже: " + Fmt.Money(st.p) + " за шт · у вас " + st.own + " из " + GameData.CompanyShares);
                }
                else
                {
                    double min = E.IpoMin(c);
                    bool can = val >= min;
                    UIKit.SetText(it.IpoInfo, can ? "IPO доступно! Какую долю продать?"
                                                  : "IPO станет доступно при оценке " + Fmt.Money(min));
                    for (int i = 0; i < it.Ipo.Length; i++)
                    {
                        double pct = GameData.IpoPercents[i];
                        it.Ipo[i].Text = "IPO " + Fmt.Pct(pct, false) + "\n" + Fmt.Money(val * pct * 0.93);
                        it.Ipo[i].Interactable = can;
                    }
                    it.Sell.Text = "Продать компанию за " + Fmt.Money(val * 0.7);
                    it.Sell.Interactable = val > 0;
                }
            }
        }
    }
}
