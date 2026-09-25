using System.Collections.Generic;
using System.Text;
using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Собственные компании: основание, найм, маркетинг, R&D, IPO, продажа
    public class CompanyScreen : GameScreen
    {
        public CompanyScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Мои компании"; } }

        string draftName = "";
        int draftInd;

        Btn[] indBtns;
        Btn foundBtn;
        TextView foundInfo;

        class Item
        {
            public int Id;
            public TextView Title, Stats, IpoInfo;
            public Btn Hire, Mkt, Rnd, Sell;
            public Btn[] Ipo;
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
            foundBtn = null;
            if (E.CanFoundCompany) BuildFound();
            else Ui.Body(Content, 15).Text = "Вы основали максимум компаний (" + GameData.CompanyFoundCost.Length + ").";
            foreach (var c in S.companies) items.Add(BuildCompany(c));
        }

        void BuildFound()
        {
            var card = Cell();
            Ui.Subtitle(card, "Основать компанию");
            Ui.Body(card, 12).Text = "Своя компания приносит выручку. Растите её, выводите на биржу (IPO) " +
                                     "и получайте деньги за продажу доли. Ваш доход пропорционален вашей доле.";

            var field = new EditText(Ui.Ctx) { Hint = "Название компании", Text = draftName };
            field.SetSingleLine(true);
            field.SetTextColor(Pal.Text);
            field.SetHintTextColor(Pal.Muted);
            field.Background = Ui.Round(Pal.CardAlt, 10);
            int p = Ui.Dp(12);
            field.SetPadding(p, p, p, p);
            field.SetFilters(new Android.Text.IInputFilter[] { new Android.Text.InputFilterLengthFilter(24) });
            field.TextChanged += (s, e) => draftName = field.Text;
            Ui.Add(card, field, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);

            var row = Ui.Row(card, 58, 6);
            indBtns = new Btn[GameData.Industries.Length];
            for (int i = 0; i < indBtns.Length; i++)
            {
                int k = i;
                var ind = GameData.Industries[i];
                indBtns[i] = Ui.Button(row, Icons.Of(ind.Id) + "\n" + ind.Name, Pal.Btn, () => { draftInd = k; Sync(); }, 11, 0);
            }
            foundInfo = Ui.Body(card, 13);
            foundBtn = Ui.Button(card, "", Pal.Gold, Found, 16, 56);
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

            var top = Ui.Row(card, 0, 12);
            Ui.Badge(top, Icons.Of(ind.Id), 50);
            var stack = Ui.Stack(top);
            it.Title = Ui.Label(stack, "", 17, Pal.Text, true);
            Ui.Label(stack, ind.Name, 13, Pal.Muted);

            it.Stats = Ui.Body(card, 14);

            it.Hire = Ui.Button(card, "", Pal.Gold, () => UI.Try(E.CoHire(E.FindCompany(it.Id)), "Недостаточно средств"), 15, 52);
            var row = Ui.Row(card, 58, 8);
            it.Mkt = Ui.Button(row, "", Pal.Blue, () => UI.Try(E.CoMarketing(E.FindCompany(it.Id)), "Недостаточно средств"), 13, 0);
            it.Rnd = Ui.Button(row, "", Pal.Blue, () => UI.Try(E.CoRnd(E.FindCompany(it.Id)), "Не хватает денег или сырья"), 12, 0);

            it.IpoInfo = Ui.Body(card, 13);
            if (!c.pub)
            {
                var irow = Ui.Row(card, 54, 8);
                it.Ipo = new Btn[GameData.IpoPercents.Length];
                for (int i = 0; i < it.Ipo.Length; i++)
                {
                    double pct = GameData.IpoPercents[i];
                    it.Ipo[i] = Ui.Button(irow, "", Pal.Green, () => AskIpo(it.Id, pct), 13, 0);
                }
                it.Sell = Ui.Button(card, "", Pal.Red, () => AskSell(it.Id), 14, 48);
            }
            else
            {
                Ui.Button(card, "Открыть на бирже", Pal.Blue, () => UI.SwitchTab(3), 15, 48);
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
                Ui.Set(foundInfo, "Отрасль: " + ind.Name + " · колебания акций ~" + Fmt.Pct(ind.Vol * 8, false) + " в минуту");
                foundBtn.Text = "Основать за " + Fmt.Money(E.FoundCost);
                foundBtn.Enabled = S.money >= E.FoundCost;
            }

            foreach (var it in items)
            {
                var c = E.FindCompany(it.Id);
                if (c == null) continue;
                double rev = E.CoRevenue(c), frac = E.CoOwnFrac(c), val = E.CoValuation(c);
                Ui.Set(it.Title, c.name + (c.pub ? "  ·  " + c.ticker : "  ·  частная"));
                Ui.Set(it.Stats, new Rich()
                    .T("Сотрудники: " + c.staff + " · Маркетинг: ур." + c.mkt + " · R&D: ур." + c.rnd).N()
                    .T("Выручка: " + Fmt.Money(rev) + "/сек · ваша доля " + Fmt.Pct(frac, false)).N()
                    .T("Ваш доход: ").C(Fmt.Money(rev * frac) + "/сек", Pal.Green).N()
                    .T("Оценка: ").B(Fmt.Money(val), Pal.Gold));

                double hc = E.CoHireCost(c), mc = E.CoMarketingCost(c);
                var rc = E.CoRndCost(c);
                it.Hire.Text = "Нанять сотрудника · " + Fmt.Money(hc);
                it.Hire.Enabled = S.money >= hc;
                it.Mkt.Text = "Маркетинг +30%\n" + Fmt.Money(mc);
                it.Mkt.Enabled = S.money >= mc;
                it.Rnd.SetRich(Cost(new Rich().T("R&D ×2\n"), rc));
                it.Rnd.Enabled = E.CanPay(rc);

                if (c.pub)
                {
                    var st = E.Stock(c.ticker);
                    Ui.Set(it.IpoInfo, "Акции на бирже: " + Fmt.Money(st.p) + " за шт · у вас " + st.own + " из " + GameData.CompanyShares);
                }
                else
                {
                    double min = E.IpoMin(c);
                    bool can = val >= min;
                    Ui.Set(it.IpoInfo, can ? "IPO доступно! Какую долю продать?"
                                           : "IPO станет доступно при оценке " + Fmt.Money(min));
                    for (int i = 0; i < it.Ipo.Length; i++)
                    {
                        double pct = GameData.IpoPercents[i];
                        it.Ipo[i].Text = "IPO " + Fmt.Pct(pct, false) + "\n" + Fmt.Money(val * pct * 0.93);
                        it.Ipo[i].Enabled = can;
                    }
                    it.Sell.Text = "Продать компанию за " + Fmt.Money(val * 0.7);
                    it.Sell.Enabled = val > 0;
                }
            }
        }
    }
}
