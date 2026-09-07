# Keep projection math canonical on the server

Laravel owns the single projection calculation and uses integer cents, integer basis points, and an explicit annual rounding rule. React submits validated household-plan assumptions and renders returned projection points rather than maintaining a second financial calculator, accepting an explicit update action instead of risking drift between PHP and TypeScript formulas.
