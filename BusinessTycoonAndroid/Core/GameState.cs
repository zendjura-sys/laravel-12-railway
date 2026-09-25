using System;
using System.Collections.Generic;

// Сохраняемое состояние игры: только публичные поля, массивы и List — сериализуется в JSON.
namespace Tycoon
{
    [Serializable]
    public class BizState
    {
        public int n;
        public int lvl;
        public bool seen;
    }

    [Serializable]
    public class StockState
    {
        public string t;
        public double p;     // текущая цена
        public double f;     // справедливая цена
        public double p0;
        public long own;     // акций у игрока
        public double avg;   // средняя цена покупки
        public List<double> h = new List<double>();
    }

    [Serializable]
    public class OptionPos
    {
        public int id;
        public string t;
        public bool call;
        public double strike;
        public int qty;
        public double expiry;
        public double paid;
    }

    [Serializable]
    public class Company
    {
        public int id;
        public string name;
        public int ind;
        public int form;       // индекс в GameData.Forms
        public int staff;
        public int mkt;
        public int rnd;
        public int seats;      // установлено оборудования рабочих мест
        public int boosts;     // установлено оборудования-ускорителей
        public double assets;  // стоимость вложенного оборудования
        public bool pub;
        public string ticker;
        public double scale;   // устарело (v1), нужно только для миграции
    }

    [Serializable]
    public class NewsItem
    {
        public string text;
        public int kind;   // 0 — нейтрально, 1 — хорошо, -1 — плохо
    }

    [Serializable]
    public class GameState
    {
        public const int Version = 2;

        public int v = Version;
        public double money = GameData.StartMoney;
        public double t;
        public double runEarned;
        public double totalEarned;
        public int rep;
        public int prestiges;
        public int clickLvl;
        public long clicks;
        public long trades;
        public int storeLvl;

        public int level = 1;
        public double xp;          // опыт внутри текущего уровня

        public double[] res;
        public bool[] auto;
        public double[] mkt;
        public BizState[] biz;
        public int[] items;        // склад купленного оборудования

        public List<StockState> stocks = new List<StockState>();
        public List<OptionPos> options = new List<OptionPos>();
        public List<Company> companies = new List<Company>();
        public List<NewsItem> news = new List<NewsItem>();

        public string holdingName;  // null — холдинга нет

        public double bankDep;
        public double bankLoan;
        public double nextEvent = 40;
        public int optId = 1;
        public int coId = 1;
        public long lastSeenUnix;

        public static GameState Create()
        {
            var s = new GameState();
            s.Normalize();
            return s;
        }

        public bool IsSupported { get { return v >= 1 && v <= Version; } }

        // Дополняет недостающие поля и переносит старые сохранения
        public void Normalize()
        {
            res = Resize(res, GameData.Resources.Length, 0.0);
            mkt = Resize(mkt, GameData.Resources.Length, 1.0);
            auto = Resize(auto, GameData.Resources.Length, false);
            items = Resize(items, GameData.Items.Length, 0);
            var oldBiz = biz ?? new BizState[0];
            biz = new BizState[GameData.Businesses.Length];
            for (int i = 0; i < biz.Length; i++) biz[i] = i < oldBiz.Length && oldBiz[i] != null ? oldBiz[i] : new BizState();
            if (stocks == null) stocks = new List<StockState>();
            if (options == null) options = new List<OptionPos>();
            if (companies == null) companies = new List<Company>();
            if (news == null) news = new List<NewsItem>();
            foreach (var d in GameData.Stocks)
            {
                if (stocks.Exists(x => x.t == d.Ticker)) continue;
                var st = new StockState { t = d.Ticker, p = d.Price, f = d.Price, p0 = d.Price };
                st.h.Add(d.Price);
                stocks.Add(st);
            }
            foreach (var st in stocks) if (st.h == null) st.h = new List<double> { st.p };
            if (level < 1) level = 1;
            if (v < 2) MigrateV1();
            v = Version;
        }

        // v1: не было уровней и оборудования, у компаний был «масштаб» и другой список отраслей
        void MigrateV1()
        {
            // уровень по уже заработанным деньгам (до первого «платного» порога)
            double xpTotal = runEarned * GameData.XpPerDollar;
            level = 1;
            while (level < GameData.GatedFromLevel && xpTotal >= GameData.XpForLevel(level))
            {
                xpTotal -= GameData.XpForLevel(level);
                level++;
            }
            xp = xpTotal;

            // старые отрасли: it, retail, food, energy, fin
            int[] indMap = { 0, 1, 2, 5, 6 };
            foreach (var c in companies)
            {
                c.ind = c.ind >= 0 && c.ind < indMap.Length ? indMap[c.ind] : 0;
                c.form = c.pub ? GameData.FormPAO : c.scale > 1 ? GameData.FormAO : GameData.FormOOO;
                var ind = GameData.Industries[c.ind];
                c.seats = (c.staff + ind.SeatsPer - 1) / ind.SeatsPer;
            }
        }

        static T[] Resize<T>(T[] a, int n, T def)
        {
            var r = new T[n];
            for (int i = 0; i < n; i++) r[i] = a != null && i < a.Length ? a[i] : def;
            return r;
        }
    }
}
