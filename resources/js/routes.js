import Home from "./pages/Home.vue";
import Report from "./pages/Report.vue";
import Responders from "./pages/Responders.vue";
import Settings from "./pages/Settings.vue";
import NotFound from "./pages/NotFound.vue";
import OnboardingPage from "./pages/OnBoarding.vue";
import Login from "./pages/Login.vue";
import MyRequests  from "./pages/MyRequests.vue";

 const routes = [
  { path: "/", component: Home },
  { path: "/report", component: Report },
  { path: "/responders", component: Responders },
  { path: "/my-requests", component: MyRequests },
  { path: "/settings", component: Settings },
  { path: "/onboarding", component: OnboardingPage },
  { path: "/login", component: Login },
  { path: "/404", component: NotFound },
  { path: "(.*)", redirect: "/404" }
];

export default routes;
