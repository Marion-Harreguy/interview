<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\Answer;
use App\Entity\Question;
use App\Entity\Interview;
use App\Entity\Interviewed;
use App\Form\InterviewType;
use App\Form\InterviewedType;
use App\Form\InterviewEditType;
use App\Repository\AnswerRepository;
use App\Repository\TagRepository;
use App\Repository\InterviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InterviewedRepository;
use App\Repository\QuestionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/api/interviews", name="interview_")
 */
class InterviewController extends AbstractController
{
    /**
     * Liste toutes les interviews
     * 
     * @Route("/", name="browse", methods={"GET"})
     */
    public function browse(InterviewRepository $interviewRepository, SerializerInterface $serializer)
    {
        $interviews = $interviewRepository->findAllPublished();

        $data = $serializer->normalize($interviews, null, ['groups' => ['browseInterviews']]);

        return $this->json(
            $data,
            $status = 200,
            $headers = ['content-type' => 'application/Json'],
            $context = []
        );
    }
    /**
     * Affiche une interview spécifique 
     * 
     * @Route("/{id}", name="read", methods={"GET"}, requirements={"id":"\d+"})
     */
    public function read($id, InterviewRepository $interviewRepository, SerializerInterface $serializer)
    {

        $interview = $interviewRepository->findCompleteInterview($id);

        $data = $serializer->normalize($interview, null, ['groups' => ['interview']]);


        return $this->json(
            $data,
            $status = 200,
            $headers = ['content-type' => 'application/Json'],
            $context = []
        );
    }
    /**
     * Modifie / met à jour une interview
     * 
     * @Route("/{id}", name="edit", methods={"PUT", "PATCH"}, requirements={"id":"\d+"})
     */
    public function edit(Interview $interview, Request $request, EntityManagerInterface $em, TagRepository $tagRepository, QuestionRepository $questionRepository, AnswerRepository $answerRepository)
    {
        $data = json_decode($request->getContent(), true);

        // verfier l'user token et l'author 

        // recupere les objet  des tags et de l'interviewé
        //dd($data);


        // faut valider les données dans le form 
        $form = $this->createForm(InterviewEditType::class, $interview);
        $form->submit($data["interview"]["meta"]);

        if ($form->isSubmitted() && $form->isValid()) {

            // on peut verifier les tags (ajout / modificatin/ suppression)
            for ($i = 0; $i < count($data["interview"]["tags"]); $i++) {
                $tagName =  $data["interview"]["tags"][$i]["name"];
                $tag = $tagRepository->findOneBy(["name" => $tagName]);
                if ($tag) {
                    $tag->addInterview($interview);
                } else {
                    $tag = new Tag();
                    $tag->setName($tagName);
                    $tag->addInterview($interview);
                }
                $em->persist($tag);
            }
            // on verifie l'interviewé on lui set l'interview
            foreach($interview->getInterviewed() as $interviewed){
                $interviewed->addInterview($interview);
            }
            
            // enfin on pour verifier les questions / réponses

           // dd($data["content"]);
            foreach ($data["content"] as $questionReponse) {
              
                if(isset($questionReponse["id"])){
                    $questionId = $questionReponse["id"];
                }else {
                    $questionId = null;
                }
             
                //dd($questionId);

                if (!$questionId){
                    $question = new Question();
                    $question->setContent($questionReponse["content"]);

                    if(isset($questionReponse["answers"]["id"])){
                        $answerId = $questionReponse["answers"]["id"];
                    }else {
                        $answerId = null;
                    }

                    if(!$answerId){
                        $answer = new Answer();
                        $answer->setContent($questionReponse["answers"]["content"]);
                        $answer->setQuestion($question);
                        $answer->setInterviewed($interviewed);
                    }else {
                        $answer = $answerRepository->find($questionReponse["answers"]["id"]);

                        $answer->setContent($questionReponse["answers"]["content"]);
                        $answer->setUpdatedAt(new \Datetime);
                    }
                    $question->setInterview($interview);
                }else {
                    $question = $questionRepository->find($questionId);

                    $question->setContent($questionReponse["content"]);
                    
                    if(!$answerId){
                        $answer = new Answer();
                        $answer->setContent($questionReponse["answers"]["content"]);
                        $answer->setQuestion($question);
                        $answer->setInterviewed($interviewed);
                       
                    }else {
                        $answer = $answerRepository->find($questionReponse["answers"]["id"]);

                        $answer->setContent($questionReponse["answers"]["content"]);
                        $answer->setUpdatedAt(new \Datetime);
                    }
                   

                    $question->setInterview($interview);
               
                }
                $em->persist($answer);
                $em->persist($question);
                
                
            }

            $interview->setUpdatedAt(new \Datetime);

            $em->persist($interview);
        }
       

  $em->flush();

            return $this->json(
            ['message' => 'bingo '],
            $status = 200,
            $headers = ['content-type' => 'application/Json'],
            $context = []
        );
    }
    /**
     * Créer une nouvelle interview
     * 
     * @Route("/", name="add", methods={"POST"})
     */
    public function add(Request $request, EntityManagerInterface $em, TagRepository $tagRepository, InterviewedRepository $interviewedRepository)
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();

        // On insancie l'objet interview
        // puis on le passe a travers la validation de données 
        // On valide les premieres données de l'interview 
        $interview = new Interview();
        $form = $this->createForm(InterviewType::class, $interview);
        $form->submit($data["interview"]);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($interview);
        }

        // on récupere les tags et on les valide 
        // on verifie si le tag existe 
        // $data["tags"] étant un tableau d'objet on vas parcourir celui ci 
        foreach ($data["tags"] as $tagUnSaved) {
            $tag = $tagRepository->findOneBy(["name" => $tagUnSaved["name"]]);
            if ($tag) {
                $tag->addInterview($interview);
            } else {
                $tag = new Tag();
                $tag->setName($tagUnSaved["name"]);
                $tag->addInterview($interview);
            }
            $em->persist($tag);
        }

        // on recupere les interviewed et on les valide
        // on verifie si il existe deja
        //dd($data["interviewed"]);
        foreach ($data["interviewed"] as $interviewedUnSaved) {
            $interviewed = $interviewedRepository->findOneBy(["email" => $interviewedUnSaved["email"]]);
            if ($interviewed) {
                $interviewed->addInterview($interview);
            } else {
                $interviewed = new Interviewed();
                $formIntervierwed = $this->createForm(InterviewedType::class, $interviewed);
                $formIntervierwed->submit($interviewedUnSaved);
                if ($formIntervierwed->isSubmitted() && $formIntervierwed->isValid()) {
                    $interviewed->addInterview($interview);
                }
            }
            $em->persist($interviewed);
        }
        $user->addInterview($interview);
        $em->persist($user);

        // Gestion du contenu de l'interview 

        //dd($data["content"]);

        // on vas parcourrir le tableau de contenu 
        for ($indexContent = 0; $indexContent < count($data["content"]); $indexContent++) {
            //dd($data["content"][$indexContent]);
            $content = $data["content"][$indexContent];
            // pour chaque tableau ainsi obtenu 
            // on vas créer une question (au besoin verifie si elle existe)
            $question = new Question();
            $answer = new Answer();
            $question->setContent($content["question"]);
            $question->addAnswer($answer);
            $question->setInterview($interview);
            // on vas enfin associé les question a l'interview
            // puis on vas créer une réponse, et y associé :
            // l'interviewed
            // la question 
            $answer->setContent($content["answers"]);
            $answer->setInterviewed($interviewed);

            $em->persist($question, $answer);
        }
        $em->flush();

        // dd($data, $interview, $user);


        return $this->json(
            ['message' => 'Interview Added'],
            $status = 200,
            $headers = ['content-type' => 'application/Json'],
            $context = []
        );
    }
    /**
     * Supprime une interview
     * 
     * @Route("/{id}", name="delete", methods={"DELETE"}, requirements={"id":"\d+"})
     */
    public function delete()
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/InterviewController.php',
        ]);
    }
}
