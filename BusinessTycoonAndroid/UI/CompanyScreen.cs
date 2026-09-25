using System.Collections.Generic;
using System.Text;
using Android.Views;
using Android.Widget;

namespace Tycoon.Droid
{
    // Свои компании: регистрация (ИП/ООО), штат и оборудование, маркетинг, R&D, реорганизация, IPO, холдинг
    public class CompanyScreen : GameScreen
    {
        public CompanyScreen(Shell ui) : base(ui) { }
        public override string Title { get { return "Мои фирмы"; } }

        string draftName = "", holdingDraft = "";
        int draftInd, draftForm;

        Btn[] indBtns, formBtns;
        Btn foundBtn, holdingBtn;
        TextView foundInfo, foundReqs, holdingText;

        class Item
        {
            public int Id;
            public TextView Title, Stats, Reorg;
            public Btn Hire, Hire10, Mkt, Rnd, Boost, ReorgBtn, Sell;
            public Btn[] Ipo;
        }

        readonly List<Item> items = new List<Item>();

        protected override string Signature()
        {
            var sb = new StringBuilder();
            sb.Append(E.CanFoundMore).Append('|').Append(E.HasHolding).Append('|');
            foreach (var c in S.companies) sb.Append(c.id).Append(c.form).Append(c.pub ? 'p' : 'c').Append(',');
            return sb.ToString();
        }

        protected override void Build()
        {
            items.Clear();
            foundBtn = null;
            Ui.Body(Content, 13).Text =
                "Выручка компании ограничена спросом и мощностью: сотрудники дают мощность, маркетинг — спрос. " +
                "Из выручки платятся зарплаты, аренда и налоги (ИП — 6% с выручки, ООО и выше — 20% с прибыли). " +
                "Убыток оплачиваете вы. Развивайтесь: ИП → ООО → АО → ПАО (IPO) → Корпорация, а затем объедините фирмы в холдинг.";
            if (E.CanFoundMore) BuildFound();
            foreach (var c in S.companies) items.Add(BuildCompany(c));
            BuildHolding();
        }

        // ---------- Регистрация ----------
        void BuildFound()
        {
            var card = Cell();
            Ui.Subtitle(card, "Зарегистрировать компанию");

            var field = new EditText(Ui.Ctx) { Hint = "Название компании", Text = draftName };
            StyleField(field);
            field.TextChanged += (s, e) => draftName = field.Text;
            Ui.Add(card, field, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);

            Ui.Label(card, "Отрасль", 14, Pal.Title, true);
            indBtns = new Btn[GameData.Industries.Length];
            LinearLayout row = null;
            for (int i = 0; i < indBtns.Length; i++)
            {
                if (i % 4 == 0) row = Ui.Row(card, 58, 6);
                int k = i;
                var ind = GameData.Industries[i];
                indBtns[i] = Ui.Button(row, IconOf(ind.Id) + "\n" + ind.Name, Pal.Btn, () => { draftInd = k; Sync(); }, 11, 0);
            }
            // пустые ячейки, чтобы кнопки последней строки не растягивались
            for (int i = indBtns.Length; i % 4 != 0; i++) Ui.Add(row, new View(Ui.Ctx), 0, 1, 1);

            Ui.Label(card, "Форма собственности", 14, Pal.Title, true);
            var frow = Ui.Row(card, 50, 8);
            formBtns = new Btn[2];
            for (int f = 0; f < 2; f++)
            {
                int k = f;
                formBtns[f] = Ui.Button(frow, GameData.Forms[f].Short, Pal.Btn, () => { draftForm = k; Sync(); }, 15, 0);
            }
            foundInfo = Ui.Body(card, 13);
            foundReqs = Ui.Body(card, 14);
            var brow = Ui.Row(card, 56, 8);
            foundBtn = Ui.Button(brow, "", Pal.Gold, Found, 16, 0);
            Ui.Button(brow, "🛒 Магазин", Pal.Blue, () => UI.SwitchTab(Shell.TabShop), 14, 0);
        }

        static void StyleField(EditText field)
        {
            field.SetSingleLine(true);
            field.SetTextColor(Pal.Text);
            field.SetHintTextColor(Pal.Muted);
            field.Background = Ui.Round(Pal.CardAlt, 10);
            int p = Ui.Dp(12);
            field.SetPadding(p, p, p, p);
            field.SetFilters(new Android.Text.IInputFilter[] { new Android.Text.InputFilterLengthFilter(24) });
        }

        void Found()
        {
            var err = E.FoundCompany(draftName, draftInd, draftForm);
            if (err != null) { UI.Toast(err); return; }
            draftName = "";
            UI.Toast("Компания зарегистрирована!");
            UI.Refresh();
        }

        // ---------- Карточка компании ----------
        Item BuildCompany(Company c)
        {
            var it = new Item { Id = c.id };
            var ind = GameData.Industries[c.ind];
            var card = Cell();

            var top = Ui.Row(card, 0, 12);
            Ui.Badge(top, IconOf(ind.Id), 50);
            var st = Ui.Stack(top);
            it.Title = Ui.Label(st, "", 17, Pal.Text, true);
            Ui.Label(st, ind.Name + " · " + GameData.Forms[c.form].Name, 12, Pal.Muted);

            it.Stats = Ui.Body(card, 14);

            var r1 = Ui.Row(card, 54, 8);
            it.Hire = Ui.Button(r1, "", Pal.Gold, () => UI.Try(Err(E.Hire(Co(it), 1)), last), 13, 0);
            it.Hire10 = Ui.Button(r1, "Нанять ×10", Pal.Gold, () => UI.Try(Err(E.Hire(Co(it), 10)), last), 13, 0);
            var r2 = Ui.Row(card, 58, 8);
            it.Mkt = Ui.Button(r2, "", Pal.Blue, () => UI.Try(E.Marketing(Co(it)), "Недостаточно средств"), 12, 0);
            it.Rnd = Ui.Button(r2, "", Pal.Blue, () => UI.Try(E.Rnd(Co(it)), "Не хватает денег или сырья"), 12, 0);
            var r3 = Ui.Row(card, 52, 8);
            it.Boost = Ui.Button(r3, "", Pal.Green, () => UI.Try(Err(E.InstallBoost(Co(it))), last), 12, 0);
            Ui.Button(r3, "🛒 Магазин", Pal.Btn, () => UI.SwitchTab(Shell.TabShop), 13, 0);

            it.Reorg = Ui.Body(card, 13);
            if (c.form + 1 == GameData.FormPAO)
            {
                var irow = Ui.Row(card, 54, 8);
                it.Ipo = new Btn[GameData.IpoPercents.Length];
                for (int i = 0; i < it.Ipo.Length; i++)
                {
                    double pct = GameData.IpoPercents[i];
                    it.Ipo[i] = Ui.Button(irow, "", Pal.Green, () => AskIpo(it.Id, pct), 12, 0);
                }
            }
            else if (E.HasNextForm(c))
            {
                it.ReorgBtn = Ui.Button(card, "", Pal.Green, () => UI.Try(Err(E.Reorganize(Co(it))), last), 14, 50);
            }

            if (!c.pub) it.Sell = Ui.Button(card, "", Pal.Red, () => AskSell(it.Id), 13, 46);
            else Ui.Button(card, "📈 Акции на бирже", Pal.Blue, () => UI.SwitchTab(Shell.TabFinance), 14, 46);
            return it;
        }

        Company Co(Item it) { return E.FindCompany(it.Id); }

        // Преобразует «ошибку или null» в bool для Try, запоминая текст ошибки
        string last = "";
        bool Err(string e) { last = e ?? ""; return e == null; }

        void AskIpo(int id, double pct)
        {
            var c = E.FindCompany(id);
            if (c == null) return;
            double val = E.CoValuation(c);
            UI.ShowModal("IPO",
                "Провести аудит, листинг и вывести «" + c.name + "» на биржу, продав " + Fmt.Pct(pct, false) + " акций?\n\n" +
                "Вы получите около " + Fmt.Money(val * pct * 0.93) + " (7% — комиссия андеррайтера). " +
                "Компания станет ПАО, ваш доход будет пропорционален вашей доле.",
                "Провести IPO", () => UI.Try(Err(E.Ipo(E.FindCompany(id), pct)), last), "Отмена");
        }

        void AskSell(int id)
        {
            var c = E.FindCompany(id);
            if (c == null) return;
            UI.ShowModal("Продажа компании",
                "Продать «" + c.name + "» стратегическому инвестору за " + Fmt.Money(E.CoValuation(c) * 0.8) + "?",
                "Продать", () => UI.Try(E.SellCompany(E.FindCompany(id)), "Нельзя продать"), "Отмена", Pal.Red);
        }

        // ---------- Холдинг ----------
        void BuildHolding()
        {
            var card = Cell();
            Ui.Subtitle(card, "🏛️ Холдинг");
            holdingText = Ui.Body(card, 14);
            holdingBtn = null;
            if (E.HasHolding) return;
            var field = new EditText(Ui.Ctx) { Hint = "Название холдинга", Text = holdingDraft };
            StyleField(field);
            field.TextChanged += (s, e) => holdingDraft = field.Text;
            Ui.Add(card, field, ViewGroup.LayoutParams.MatchParent, ViewGroup.LayoutParams.WrapContent);
            holdingBtn = Ui.Button(card, "Создать холдинг", Pal.Gold,
                () => UI.Try(Err(E.CreateHolding(holdingDraft)), last), 16, 54);
        }

        protected override void Sync()
        {
            if (foundBtn != null)
            {
                for (int i = 0; i < indBtns.Length; i++)
                {
                    bool locked = S.level < GameData.Industries[i].ReqLevel;
                    indBtns[i].SetColor(i == draftInd ? Pal.Green : locked ? Pal.BtnOff : Pal.Btn);
                }
                for (int f = 0; f < formBtns.Length; f++) formBtns[f].SetColor(f == draftForm ? Pal.Green : Pal.Btn);
                var ind = GameData.Industries[draftInd];
                var form = GameData.Forms[draftForm];
                var info = new Rich()
                    .B(ind.Name, Pal.Text).T(": выручка $" + Fmt.Num(ind.RevPerStaff * form.Mult) + "/с и зарплата $" +
                        Fmt.Num(ind.Wage * form.Mult) + "/с на сотрудника").N()
                    .T("Рабочее место: " + IconOf(GameData.Items[ind.SeatItem].Id) + " " + GameData.Items[ind.SeatItem].Name +
                       " (" + ind.SeatsPer + " чел.)").N()
                    .B(form.Short, Pal.Text).T(" — " + form.Desc);
                Ui.Set(foundInfo, info);
                var reqs = E.FoundRequirements(draftInd, draftForm);
                Ui.Set(foundReqs, ReqText(reqs));
                foundBtn.Text = "Зарегистрировать " + form.Short;
                foundBtn.Enabled = reqs.TrueForAll(r => r.Ok) && draftName.Trim().Length > 0;
            }

            foreach (var it in items)
            {
                var c = E.FindCompany(it.Id);
                if (c == null) continue;
                var ind = E.IndOf(c);
                var form = E.FormOf(c);
                var s = E.Stats(c);
                double frac = E.CoOwnFrac(c);
                Ui.Set(it.Title, form.Short + " «" + c.name + "»" + (c.pub ? " · " + c.ticker : ""));

                var r = new Rich();
                r.T("Штат: " + c.staff + " · мест: " + s.Seats + " · лимит " + form.Short + ": " +
                    (form.MaxStaff >= 1000000 ? "∞" : form.MaxStaff.ToString())).N();
                r.T("Мощность " + Fmt.Money(s.Capacity) + "/с · спрос " + Fmt.Money(s.Demand) + "/с · загрузка ")
                 .C(Fmt.Pct(s.Load, false), s.Load > 0.9 ? Pal.Green : Pal.Gold).N();
                r.T("Выручка ").B(Fmt.Money(s.Revenue) + "/с", Pal.Text).N();
                r.T("Зарплаты -" + Fmt.Money(s.Wages) + " · аренда -" + Fmt.Money(s.Fixed) + " · налог -" + Fmt.Money(s.Tax)).N();
                r.T("Чистая прибыль ").B(Ui.Signed(s.Net) + "/с", Ui.Sign(s.Net));
                if (c.pub) r.T(" · ваша доля " + Fmt.Pct(frac, false));
                r.N().T("Оценка ").B(Fmt.Money(E.CoValuation(c)), Pal.Gold)
                 .T(" · маркетинг ур." + c.mkt + " · R&D ур." + c.rnd);
                if (E.Crunch.Contains(c.id)) r.N().C("⚠ Кассовый разрыв: не хватает денег на зарплаты!", Pal.Red);
                else if (s.Load < 0.6) r.N().C("Спроса мало для такого штата — вложитесь в маркетинг", Pal.Gold);
                else if (c.staff >= s.Seats) r.N().C("Нет свободных мест: купите «" + GameData.Items[ind.SeatItem].Name + "»", Pal.Gold);
                Ui.Set(it.Stats, r);

                double hc = E.HireCost(c);
                it.Hire.Text = "Нанять\n" + Fmt.Money(hc);
                it.Hire.Enabled = S.money >= hc && c.staff < form.MaxStaff &&
                                  (c.staff < s.Seats || S.items[ind.SeatItem] > 0);
                it.Hire10.Enabled = it.Hire.View.Enabled;
                it.Mkt.Text = "Маркетинг +спрос\n" + Fmt.Money(E.MarketingCost(c));
                it.Mkt.Enabled = S.money >= E.MarketingCost(c);
                var rc = E.RndCost(c);
                it.Rnd.SetRich(Cost(new Rich().T("R&D ×1.5\n"), rc));
                it.Rnd.Enabled = E.CanPay(rc);
                var boost = GameData.Items[ind.BoostItem];
                it.Boost.Text = IconOf(boost.Id) + " " + boost.Name + " +" + Fmt.Pct(ind.BoostPct, false) +
                                "\n" + c.boosts + "/" + ind.BoostMax + " · на складе " + S.items[ind.BoostItem];
                it.Boost.Enabled = c.boosts < ind.BoostMax && S.items[ind.BoostItem] > 0;

                if (E.HasNextForm(c))
                {
                    var next = GameData.Forms[c.form + 1];
                    var reqs = E.ReorgRequirements(c);
                    bool ok = reqs.TrueForAll(q => q.Ok);
                    Ui.Set(it.Reorg, new Rich().B("Следующий шаг: " + next.Short, Pal.Title).T(" — " + next.Desc).N().R(ReqText(reqs)));
                    if (it.ReorgBtn != null)
                    {
                        it.ReorgBtn.Text = "Реорганизовать в " + next.Short;
                        it.ReorgBtn.Enabled = ok;
                    }
                    if (it.Ipo != null)
                    {
                        double val = E.CoValuation(c);
                        for (int i = 0; i < it.Ipo.Length; i++)
                        {
                            double pct = GameData.IpoPercents[i];
                            it.Ipo[i].Text = "IPO " + Fmt.Pct(pct, false) + "\n" + Fmt.Money(val * pct * 0.93);
                            it.Ipo[i].Enabled = ok;
                        }
                    }
                }
                else
                {
                    Ui.Set(it.Reorg, "Высшая форма: корпорация 🌆");
                }
                if (it.Sell != null)
                {
                    it.Sell.Text = "Продать компанию за " + Fmt.Money(E.CoValuation(c) * 0.8);
                    it.Sell.Enabled = true;
                }
            }

            // холдинг
            var h = new Rich();
            if (E.HasHolding)
            {
                h.T("Холдинг ").B("«" + S.holdingName + "»", Pal.Gold).N()
                 .T("Бонус к выручке всех фирм: +" + Fmt.Pct(E.HoldingBonus, false)).N()
                 .T("Налог на прибыль в группе: " + Fmt.Pct(GameData.HoldingTax, false));
            }
            else
            {
                h.T("Управляющая компания над вашими фирмами: +" + Fmt.Pct(GameData.HoldingBonusPer, false) +
                    " выручки за каждую фирму (до +" + Fmt.Pct(GameData.HoldingBonusMax, false) + ") и налог " +
                    Fmt.Pct(GameData.HoldingTax, false) + " вместо 20%.").N();
                var reqs = E.HoldingRequirements();
                h.R(ReqText(reqs));
                if (holdingBtn != null) holdingBtn.Enabled = reqs.TrueForAll(q => q.Ok);
            }
            Ui.Set(holdingText, h);
        }
    }
}
