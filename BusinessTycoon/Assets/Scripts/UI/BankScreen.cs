using UnityEngine;
using UnityEngine.UI;

namespace Tycoon.UI
{
    // Банк: вклад под процент и кредит под залог капитала
    public class BankScreen : GameScreen
    {
        public BankScreen(UIRoot ui) : base(ui) { }
        public override string Title { get { return "Банк"; } }

        Text depText, loanText;
        UIKit.Btn dep25, depAll, wd50, wdAll, loan25, loanMax, repay50, repayAll;

        protected override void Build()
        {
            var dc = Cell();
            Subtitle(dc, "Вклад");
            depText = Body(dc, "", 30);
            var r1 = UIKit.Row(dc, 110, 14);
            dep25 = UIKit.Button(r1, "Внести 25%", Pal.Green, () => UI.Try(E.Deposit(S.money * 0.25), "Нет денег"), 30, 0);
            depAll = UIKit.Button(r1, "Внести всё", Pal.Green, () => UI.Try(E.Deposit(S.money), "Нет денег"), 30, 0);
            var r2 = UIKit.Row(dc, 110, 14);
            wd50 = UIKit.Button(r2, "Снять 50%", Pal.Blue, () => UI.Try(E.Withdraw(S.bankDep * 0.5), "Вклад пуст"), 30, 0);
            wdAll = UIKit.Button(r2, "Снять всё", Pal.Blue, () => UI.Try(E.Withdraw(S.bankDep), "Вклад пуст"), 30, 0);

            var lc = Cell();
            Subtitle(lc, "Кредит");
            loanText = Body(lc, "", 30);
            var r3 = UIKit.Row(lc, 110, 14);
            loan25 = UIKit.Button(r3, "", Pal.Gold, () => UI.Try(E.TakeLoan((E.LoanLimit - S.bankLoan) * 0.25), "Лимит исчерпан"), 28, 0);
            loanMax = UIKit.Button(r3, "", Pal.Gold, () => UI.Try(E.TakeLoan(E.LoanLimit - S.bankLoan), "Лимит исчерпан"), 28, 0);
            var r4 = UIKit.Row(lc, 110, 14);
            repay50 = UIKit.Button(r4, "Погасить 50%", Pal.Blue, () => UI.Try(E.Repay(S.bankLoan * 0.5), "Нечем гасить"), 30, 0);
            repayAll = UIKit.Button(r4, "Погасить всё", Pal.Blue, () => UI.Try(E.Repay(S.bankLoan), "Нечем гасить"), 30, 0);

            var tip = Body(Content, "Совет: кредит выгоден, если бизнес окупится быстрее, чем растут проценты. " +
                                    "Вклад — безопасное место для денег, которые пока не на что потратить.", 26);
            UIKit.LE(tip, -1);
        }

        protected override void Sync()
        {
            double depPerMin = S.bankDep * GameData.DepositRate * 60;
            UIKit.SetText(depText,
                "На вкладе: " + UIKit.Col(Fmt.Money(S.bankDep), Pal.Gold) + "\n" +
                "Ставка: " + Fmt.Pct(GameData.DepositRate * 60, false) + " в минуту · +" + Fmt.Money(depPerMin) + "/мин");
            dep25.Interactable = depAll.Interactable = S.money > 0.01;
            wd50.Interactable = wdAll.Interactable = S.bankDep > 0.01;

            double limit = E.LoanLimit, free = System.Math.Max(0, limit - S.bankLoan);
            UIKit.SetText(loanText,
                "Долг: " + UIKit.Col(Fmt.Money(S.bankLoan), S.bankLoan > 0 ? Pal.Red : Pal.Text) + "\n" +
                "Лимит: " + Fmt.Money(limit) + " (50% капитала) · доступно " + Fmt.Money(free) + "\n" +
                "Ставка: " + Fmt.Pct(GameData.LoanRate * 60, false) + " в минуту");
            loan25.Text = "Взять 25%\n" + Fmt.Money(free * 0.25);
            loanMax.Text = "Взять максимум\n" + Fmt.Money(free);
            loan25.Interactable = loanMax.Interactable = free > 1;
            repay50.Interactable = repayAll.Interactable = S.bankLoan > 0.01 && S.money > 0.01;
        }
    }
}
