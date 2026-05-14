import Home from "./pages/Home.vue";
import Report from "./pages/Report.vue";
import Responders from "./pages/Responders.vue";
import Settings from "./pages/Settings.vue";
import NotFound from "./pages/NotFound.vue";
import OnboardingPage from "./pages/OnBoarding.vue";
import Login from "./pages/Login.vue";
import MyRequests  from "./pages/MyRequests.vue";
import ResolvedEmergencies from "./pages/ResolvedEmergencies.vue";

 const routes = [
    { path: "/login", component: Login },
    {   path: "/onboarding", component: OnboardingPage },

    { path: "/", component: Home },
    { path: "/report", component: Report },
    { path: "/responders", component: Responders },
    { path: "/resolved-emergencies", component: ResolvedEmergencies },
    { path: "/my-requests", component: MyRequests },
    { path: "/settings", component: Settings },
    { path: "/404", component: NotFound },
    { path: "(.*)", redirect: "/404" }
];

export default routes;
