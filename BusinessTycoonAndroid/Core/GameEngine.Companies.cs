using System;
using System.Collections.Generic;

// Магазин оборудования, собственные компании (ИП → ООО → АО → ПАО → Корпорация) и холдинг.
//
// Экономика компании:
//   мощность  = сотрудники × выработка × масштаб формы × R&D × оборудование
//   спрос     = ёмкость рынка × масштаб формы^1.3 × маркетинг × R&D
//   выручка   = min(мощность, спрос)       — лишние сотрудники без спроса не окупаются
//   расходы   = зарплаты + постоянные расходы (аренда, коммуналка)
//   налог     = 6% с выручки (ИП, УСН) или 20% с прибыли (15% в холдинге)
//   владельцу = чистая прибыль × доля (после IPO — только своя доля). Убыток оплачивается из кармана.
namespace Tycoon
{
    public class Req
    {
        public string Text;
        public bool Ok;
        public Req(string text, bool ok) { Text = text; Ok = ok; }
    }

    public class CoStats
    {
        public double Capacity, Demand, Revenue, Wages, Fixed, Tax, Profit, Net, Load;
        public int Seats;
    }

    public partial class GameEngine
    {
        // Компании, которым не хватило денег на зарплаты (кассовый разрыв)
        public readonly HashSet<int> Crunch = new HashSet<int>();

        // ---------- Магазин ----------
        public bool ItemUnlocked(int i) { return S.level >= GameData.Items[i].ReqLevel; }

        public bool BuyItem(int i, int n = 1)
        {
            if (!ItemUnlocked(i) || n < 1) return false;
            if (!Spend(GameData.Items[i].Price * n)) return false;
            S.items[i] += n;
            AddXp(5 * n);
            return true;
        }

        // Продажа б/у оборудования за 50%
        public bool SellItem(int i)
        {
            if (S.items[i] < 1) return false;
            S.items[i]--;
            S.money += GameData.Items[i].Price * 0.5;
            return true;
        }

        public int ItemCount(int i) { return S.items[i]; }

        static string ItemName(int i) { return GameData.Items[i].Name; }

        void AddItemReqs(List<Req> reqs, int[] pairs, int extraSame = -1, int extraCount = 0)
        {
            for (int k = 0; k < pairs.Length; k += 2)
            {
                int item = pairs[k], cnt = pairs[k + 1];
                if (item == extraSame) cnt += extraCount;
                reqs.Add(new Req(ItemName(item) + " × " + cnt + " (есть " + S.items[item] + ")", S.items[item] >= cnt));
            }
        }

        // Все товары, нужные для открытия (стартовый комплект отрасли + требования формы)
        List<int[]> FoundItemLists(int ind, int form)
        {
            return new List<int[]> { GameData.Industries[ind].StartItems, GameData.Forms[form].Items };
        }

        // ---------- Параметры компании ----------
        public FormDef FormOf(Company c) { return GameData.Forms[c.form]; }
        public IndustryDef IndOf(Company c) { return GameData.Industries[c.ind]; }
        public Company FindCompany(int id) { return S.companies.Find(c => c.id == id); }
        public bool HasHolding { get { return S.holdingName != null; } }

        public double HoldingBonus
        {
            get { return HasHolding ? Math.Min(GameData.HoldingBonusMax, GameData.HoldingBonusPer * S.companies.Count) : 0; }
        }

        public int Seats(Company c) { return c.seats * IndOf(c).SeatsPer; }

        public CoStats Stats(Company c)
        {
            var ind = IndOf(c);
            var form = FormOf(c);
            var s = new CoStats { Seats = Seats(c) };
            double boost = 1 + c.boosts * ind.BoostPct;
            double common = RepMult * (1 + HoldingBonus);
            s.Capacity = c.staff * ind.RevPerStaff * form.Mult * Math.Pow(1.5, c.rnd) * boost * common;
            s.Demand = ind.Demand * Math.Pow(form.Mult, 1.3) * (1 + 0.5 * c.mkt) * Math.Pow(1.25, c.rnd) * common;
            s.Revenue = Math.Min(s.Capacity, s.Demand);
            s.Wages = c.staff * ind.Wage * form.Mult;
            s.Fixed = ind.Fixed * form.Mult;
            s.Profit = s.Revenue - s.Wages - s.Fixed;
            double rate = form.TaxOnRevenue ? form.TaxRate : HasHolding ? GameData.HoldingTax : form.TaxRate;
            s.Tax = form.TaxOnRevenue ? s.Revenue * rate : Math.Max(0, s.Profit) * rate;
            s.Net = s.Profit - s.Tax;
            s.Load = s.Capacity > 0 ? s.Revenue / s.Capacity : 0;
            return s;
        }

        public double CoValuation(Company c)
        {
            var s = Stats(c);
            // ~30 минут чистой прибыли + 5 минут выручки + оборудование
            return Math.Max(0, s.Net) * 1800 + s.Revenue * 300 + c.assets * 0.6;
        }

        public double CoOwnFrac(Company c)
        {
            if (!c.pub) return 1;
            var st = Stock(c.ticker);
            return st == null ? 1 : (double)st.own / GameData.CompanyShares;
        }

        // Прибыль всех компаний за шаг; возвращает то, что получил (или потерял) владелец
        double TickCompanies(double dt)
        {
            double total = 0;
            foreach (var c in S.companies)
            {
                double share = Stats(c).Net * CoOwnFrac(c) * dt;
                if (share >= 0)
                {
                    Crunch.Remove(c.id);
                    Earn(share);
                }
                else if (S.money + share >= 0)
                {
                    Crunch.Remove(c.id);
                    S.money += share;
                }
                else
                {
                    // денег на зарплаты не хватило: платим, сколько есть
                    share = -S.money;
                    S.money = 0;
                    Crunch.Add(c.id);
                }
                total += share;
            }
            return total;
        }

        // ---------- Основание ----------
        public bool CanFoundMore { get { return S.companies.Count < GameData.MaxCompanies; } }

        public List<Req> FoundRequirements(int ind, int form)
        {
            var d = GameData.Industries[ind];
            var f = GameData.Forms[form];
            var reqs = new List<Req>();
            int lvl = Math.Max(d.ReqLevel, f.ReqLevel);
            reqs.Add(new Req("Уровень " + lvl, S.level >= lvl));
            reqs.Add(new Req("Регистрация " + f.Short + ": " + Fmt.Money(f.Fee), S.money >= f.Fee));
            foreach (var list in FoundItemLists(ind, form)) AddItemReqs(reqs, list);
            reqs.Add(new Req("Свободный слот (" + S.companies.Count + " из " + GameData.MaxCompanies + ")", CanFoundMore));
            return reqs;
        }

        static bool AllOk(List<Req> reqs)
        {
            foreach (var r in reqs) if (!r.Ok) return false;
            return true;
        }

        // Возвращает текст ошибки или null
        public string FoundCompany(string name, int ind, int form)
        {
            if (form != GameData.FormIP && form != GameData.FormOOO) return "Сразу можно открыть только ИП или ООО";
            if (ind < 0 || ind >= GameData.Industries.Length) return "Выберите отрасль";
            name = (name ?? "").Trim();
            if (name.Length > 24) name = name.Substring(0, 24);
            if (name.Length == 0) return "Введите название компании";
            if (!AllOk(FoundRequirements(ind, form))) return "Выполнены не все условия";

            var d = GameData.Industries[ind];
            var c = new Company { id = S.coId++, name = name, ind = ind, form = form, staff = 1 };
            foreach (var list in FoundItemLists(ind, form))
            {
                for (int k = 0; k < list.Length; k += 2)
                {
                    int item = list[k], cnt = list[k + 1];
                    S.items[item] -= cnt;
                    c.assets += GameData.Items[item].Price * cnt;
                    if (item == d.SeatItem) c.seats += cnt;
                    if (item == d.BoostItem) c.boosts += cnt;
                }
            }
            S.money -= GameData.Forms[form].Fee;
            S.companies.Add(c);
            AddXp(100 * (form + 1));
            AddNews("Зарегистрирована компания " + GameData.Forms[form].Short + " «" + name + "»", 1);
            return null;
        }

        // ---------- Развитие ----------
        public double HireCost(Company c) { return IndOf(c).HireFee * FormOf(c).Mult * Math.Pow(1.03, c.staff); }
        public double MarketingCost(Company c) { return IndOf(c).HireFee * FormOf(c).Mult * 4 * Math.Pow(1.7, c.mkt); }

        public UpgradeCost RndCost(Company c)
        {
            double money = IndOf(c).HireFee * FormOf(c).Mult * 40 * Math.Pow(3.5, c.rnd);
            return new UpgradeCost { Money = money, Mat = MatFor(money) };
        }

        // Найм n сотрудников; рабочие места берутся из купленного оборудования
        public string Hire(Company c, int n = 1)
        {
            var ind = IndOf(c);
            int hired = 0;
            string err = null;
            for (int k = 0; k < n; k++)
            {
                if (c.staff >= FormOf(c).MaxStaff) { err = "Лимит штата для " + FormOf(c).Short + " — реорганизуйте компанию"; break; }
                if (c.staff >= Seats(c))
                {
                    if (S.items[ind.SeatItem] < 1) { err = "Нет рабочего места: купите «" + ItemName(ind.SeatItem) + "» в магазине"; break; }
                    S.items[ind.SeatItem]--;
                    c.seats++;
                    c.assets += GameData.Items[ind.SeatItem].Price;
                }
                if (!Spend(HireCost(c))) { err = "Недостаточно денег на подбор персонала"; break; }
                c.staff++;
                hired++;
            }
            if (hired > 0) AddXp(10 * hired);
            return hired > 0 ? null : err;
        }

        public string InstallBoost(Company c)
        {
            var ind = IndOf(c);
            if (c.boosts >= ind.BoostMax) return "Установлено максимальное количество";
            if (S.items[ind.BoostItem] < 1) return "Купите «" + ItemName(ind.BoostItem) + "» в магазине";
            S.items[ind.BoostItem]--;
            c.boosts++;
            c.assets += GameData.Items[ind.BoostItem].Price;
            AddXp(20);
            return null;
        }

        public bool Marketing(Company c)
        {
            if (!Spend(MarketingCost(c))) return false;
            c.mkt++;
            AddXp(15 * c.mkt);
            return true;
        }

        public bool Rnd(Company c)
        {
            if (!Pay(RndCost(c))) return false;
            c.rnd++;
            AddXp(50 * c.rnd);
            return true;
        }

        // ---------- Реорганизация ----------
        public bool HasNextForm(Company c) { return c.form < GameData.Forms.Length - 1; }

        public List<Req> ReorgRequirements(Company c)
        {
            var reqs = new List<Req>();
            if (!HasNextForm(c)) return reqs;
            var f = GameData.Forms[c.form + 1];
            reqs.Add(new Req("Уровень " + f.ReqLevel, S.level >= f.ReqLevel));
            if (f.MinStaff > 0) reqs.Add(new Req("Штат от " + f.MinStaff + " (сейчас " + c.staff + ")", c.staff >= f.MinStaff));
            if (f.MinValuation > 0)
                reqs.Add(new Req("Оценка от " + Fmt.Money(f.MinValuation) + " (сейчас " + Fmt.Money(CoValuation(c)) + ")", CoValuation(c) >= f.MinValuation));
            reqs.Add(new Req((c.form + 1 == GameData.FormPAO ? "Аудит и листинг: " : "Взнос: ") + Fmt.Money(f.Fee), S.money >= f.Fee));
            AddItemReqs(reqs, f.Items);
            return reqs;
        }

        bool PayReorg(Company c)
        {
            var f = GameData.Forms[c.form + 1];
            if (!AllOk(ReorgRequirements(c))) return false;
            S.money -= f.Fee;
            for (int k = 0; k < f.Items.Length; k += 2)
            {
                S.items[f.Items[k]] -= f.Items[k + 1];
                c.assets += GameData.Items[f.Items[k]].Price * f.Items[k + 1];
            }
            c.form++;
            AddXp(500 * c.form);
            return true;
        }

        // ИП → ООО → АО, ПАО → Корпорация (в ПАО — через Ipo)
        public string Reorganize(Company c)
        {
            if (!HasNextForm(c)) return "Это высшая форма";
            if (c.form + 1 == GameData.FormPAO) return "Переход в ПАО — через IPO";
            if (!PayReorg(c)) return "Выполнены не все условия";
            AddNews("«" + c.name + "» реорганизована в " + FormOf(c).Short, 1);
            return null;
        }

        public string MakeTicker(string name)
        {
            var map = "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";
            var lat = new[] { "A","B","V","G","D","E","E","Z","Z","I","I","K","L","M","N","O","P","R","S","T","U","F","H","C","C","S","S","","Y","","E","U","A" };
            var sb = new System.Text.StringBuilder();
            foreach (var ch0 in name.ToLowerInvariant())
            {
                if (ch0 >= 'a' && ch0 <= 'z') sb.Append(char.ToUpperInvariant(ch0));
                else { int k = map.IndexOf(ch0); if (k >= 0) sb.Append(lat[k]); }
                if (sb.Length >= 4) break;
            }
            var t = (sb.ToString() + "XXXX").Substring(0, 4);
            var outT = t;
            int i = 1;
            while (Stock(outT) != null) outT = t.Substring(0, 3) + (i++ % 10);
            return outT;
        }

        // IPO: АО становится ПАО и продаёт часть акций инвесторам
        public string Ipo(Company c, double pct)
        {
            if (c == null || c.pub) return "Компания уже публичная";
            if (c.form + 1 != GameData.FormPAO) return "IPO проводит только АО";
            if (!PayReorg(c)) return "Выполнены не все условия";
            double val = CoValuation(c);
            c.pub = true;
            c.ticker = MakeTicker(c.name);
            double price = Math.Max(0.01, val / GameData.CompanyShares);
            var st = new StockState
            {
                t = c.ticker, p = price, f = price, p0 = price, avg = price,
                own = (long)Math.Round(GameData.CompanyShares * (1 - pct)),
            };
            st.h.Add(price);
            S.stocks.Add(st);
            double cash = val * pct * 0.93;
            Earn(cash);
            AddNews("IPO! ПАО «" + c.name + "» (" + c.ticker + ") на бирже. Привлечено " + Fmt.Money(cash), 1);
            return null;
        }

        public bool SellCompany(Company c)
        {
            if (c == null || c.pub) return false;
            double v = CoValuation(c) * 0.8;
            Earn(v);
            AddNews("Компания «" + c.name + "» продана за " + Fmt.Money(v));
            S.companies.Remove(c);
            Crunch.Remove(c.id);
            return true;
        }

        // ---------- Холдинг ----------
        public List<Req> HoldingRequirements()
        {
            var reqs = new List<Req>();
            int big = S.companies.FindAll(c => c.form >= GameData.FormOOO).Count;
            reqs.Add(new Req("Уровень " + GameData.HoldingLevel, S.level >= GameData.HoldingLevel));
            reqs.Add(new Req("Компаний ООО и выше: " + big + " из " + GameData.HoldingMinCompanies, big >= GameData.HoldingMinCompanies));
            reqs.Add(new Req("Регистрация управляющей компании: " + Fmt.Money(GameData.HoldingFee), S.money >= GameData.HoldingFee));
            int item = GameData.HoldingItem;
            reqs.Add(new Req(ItemName(item) + " × 1 (есть " + S.items[item] + ")", S.items[item] >= 1));
            return reqs;
        }

        public string CreateHolding(string name)
        {
            if (HasHolding) return "Холдинг уже создан";
            name = (name ?? "").Trim();
            if (name.Length == 0) return "Введите название холдинга";
            if (name.Length > 24) name = name.Substring(0, 24);
            if (!AllOk(HoldingRequirements())) return "Выполнены не все условия";
            S.money -= GameData.HoldingFee;
            S.items[GameData.HoldingItem]--;
            S.holdingName = name;
            AddXp(5000);
            AddNews("Создан холдинг «" + name + "»: +5% выручки за каждую компанию, налог 15%", 1);
            StructureDirty = true;
            return null;
        }
    }
}
