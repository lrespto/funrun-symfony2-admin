<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\Teacher;
use AppBundle\Entity\Grade;
use AppBundle\Entity\Campaign;
use AppBundle\Utils\ValidationHelper;
use AppBundle\Utils\CSVHelper;
use AppBundle\Utils\CampaignHelper;
use AppBundle\Utils\QueryHelper;
use DateTime;

/**
 * Teacher controller.
 *
 * @Route("/{campaignUrl}/teachers")
 */
class TeacherController extends Controller
{
  /**
   * Lists all Teacher entities.
   *
   * @Route("/", name="teacher_index")
   * @Method({"GET", "POST"})
   */
  public function teacherIndexAction($campaignUrl)
  {
      $logger = $this->get('logger');
      $entity = 'Teacher';
      $em = $this->getDoctrine()->getManager();

      //CODE TO CHECK TO SEE IF CAMPAIGN EXISTS
      $campaign = $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl);
      if(is_null($campaign)){
        $this->get('session')->getFlashBag()->add('warning', 'We are sorry, we could not find this campaign.');
        return $this->redirectToRoute('homepage');
      }

      if(!$this->campaignPermissionsCheck($campaign)){
          $this->get('session')->getFlashBag()->add('warning', 'You do not have permissions to this campaign.');
          return $this->redirectToRoute('homepage');
      }

      $queryHelper = new QueryHelper($em, $logger);
      $tempDate = new DateTime();
      $dateString = $tempDate->format('Y-m-d').' 00:00:00';
      $reportDate = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
      // replace this example code with whatever you need
      return $this->render('campaign/teacher.index.html.twig', array(
        'teachers' => $queryHelper->getTeacherRanks(array('campaign' => $campaign, 'limit'=> 0)),
        'entity' => strtolower($entity),
        'campaign' => $campaign,
      ));

  }

    /**
     * Creates a new Teacher entity.
     *
     * @Route("/new", name="teacher_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request, $campaignUrl)
    {
        $entity = 'Teacher';
        $em = $this->getDoctrine()->getManager();

        //CODE TO CHECK TO SEE IF CAMPAIGN EXISTS
        $campaign = $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl);
        if(is_null($campaign)){
          $this->get('session')->getFlashBag()->add('warning', 'We are sorry, we could not find this campaign.');
          return $this->redirectToRoute('homepage');
        }

        if(!$this->campaignPermissionsCheck($campaign)){
            $this->get('session')->getFlashBag()->add('warning', 'You do not have permissions to this campaign.');
            return $this->redirectToRoute('homepage');
        }

        $teacher = new Teacher();
        $form = $this->createForm('AppBundle\Form\TeacherType', $teacher);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($teacher);
            $em->flush();

            return $this->redirectToRoute('teacher_index', array('id' => $teacher->getId()));
        }

        return $this->render('crud/new.html.twig', array(
            'teacher' => $teacher,
            'form' => $form->createView(),
            'entity' => $entity,
            'campaign' => $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl),
        ));
    }

    /**
     * Finds and displays a Teacher entity.
     *
     * @Route("/{id}", name="teacher_show")
     * @Method("GET")
     */
    public function showAction(Teacher $teacher, $campaignUrl)
    {
        $logger = $this->get('logger');
        $entity = 'Teacher';
        $em = $this->getDoctrine()->getManager();

        //CODE TO CHECK TO SEE IF CAMPAIGN EXISTS
        $campaign = $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl);
        if(is_null($campaign)){
          $this->get('session')->getFlashBag()->add('warning', 'We are sorry, we could not find this campaign.');
          return $this->redirectToRoute('homepage');
        }

        if(!$this->campaignPermissionsCheck($campaign)){
            $this->get('session')->getFlashBag()->add('warning', 'You do not have permissions to this campaign.');
            return $this->redirectToRoute('homepage');
        }

        $deleteForm = $this->createDeleteForm($teacher,$campaignUrl);
        $teacher = $this->getDoctrine()->getRepository('AppBundle:'.strtolower($entity))->findOneById($teacher->getId());
        //$logger->debug(print_r($student->getDonations()));

        $qb = $em->createQueryBuilder()->select('u')
               ->from('AppBundle:Campaignaward', 'u')
               ->andWhere('u.campaign = :campaignId')
               ->setParameter('campaignId', $campaign->getId())
               ->orderBy('u.amount', 'DESC');

        $campaignAwards = $qb->getQuery()->getResult();

        $queryHelper = new QueryHelper($em, $logger);

        return $this->render('campaign/teacher.show.html.twig', array(
            'teacher' => $teacher,
            'teacher_rank' => $queryHelper->getTeacherRank($teacher->getId(),array('campaign' => $campaign, 'limit' => 0)),
            'campaign_awards' => $campaignAwards,
            'delete_form' => $deleteForm->createView(),
            'entity' => $entity,
            'campaign' => $campaign,
        ));
    }

    /**
     * Displays a form to edit an existing Teacher entity.
     *
     * @Route("/edit/{id}", name="teacher_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Teacher $teacher, $campaignUrl)
    {
        $entity = 'Teacher';
        $em = $this->getDoctrine()->getManager();

        //CODE TO CHECK TO SEE IF CAMPAIGN EXISTS
        $campaign = $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl);
        if(is_null($campaign)){
          $this->get('session')->getFlashBag()->add('warning', 'We are sorry, we could not find this campaign.');
          return $this->redirectToRoute('homepage');
        }

        if(!$this->campaignPermissionsCheck($campaign)){
            $this->get('session')->getFlashBag()->add('warning', 'You do not have permissions to this campaign.');
            return $this->redirectToRoute('homepage');
        }

        $deleteForm = $this->createDeleteForm($teacher, $campaignUrl);
        $editForm = $this->createForm('AppBundle\Form\TeacherType', $teacher);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($teacher);
            $em->flush();

            return $this->redirectToRoute('teacher_index', array('id' => $teacher->getId()));
        }

        return $this->render('crud/edit.html.twig', array(
            'teacher' => $teacher,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'entity' => $entity,
            'campaign' => $campaign
        ));
    }

    /**
     * Deletes a Teacher entity.
     *
     * @Route("/delete/{id}", name="teacher_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Teacher $teacher, $campaignUrl)
    {
        $entity = 'Teacher';
        $em = $this->getDoctrine()->getManager();

        //CODE TO CHECK TO SEE IF CAMPAIGN EXISTS
        $campaign = $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl);
        if(is_null($campaign)){
          $this->get('session')->getFlashBag()->add('warning', 'We are sorry, we could not find this campaign.');
          return $this->redirectToRoute('homepage');
        }

        if(!$this->campaignPermissionsCheck($campaign)){
            $this->get('session')->getFlashBag()->add('warning', 'You do not have permissions to this campaign.');
            return $this->redirectToRoute('homepage');
        }

        $form = $this->createDeleteForm($teacher);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($teacher);
            $em->flush();
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * Creates a form to delete a Teacher entity.
     *
     * @param Teacher $teacher The Teacher entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Teacher $teacher, $campaignUrl)
    {
        $entity = 'Teacher';

        return $this->createFormBuilder()
            ->setAction($this->generateUrl('campaign_delete', array('campaignUrl'=> $campaignUrl, 'id' => $teacher->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * Creates a new Teacher entity.
     *
     * @Route("/upload", name="teacher_upload")
     * @Method({"GET", "POST"})
     */
    public function uploadForm(Request $request)
    {
        $logger = $this->get('logger');
        $entity = 'Teacher';
        $em = $this->getDoctrine()->getManager();

        //CODE TO CHECK TO SEE IF CAMPAIGN EXISTS
        $campaign = $em->getRepository('AppBundle:Campaign')->findOneByUrl($campaignUrl);
        if(is_null($campaign)){
          $this->get('session')->getFlashBag()->add('warning', 'We are sorry, we could not find this campaign.');
          return $this->redirectToRoute('homepage');
        }

        if(!$this->campaignPermissionsCheck($campaign)){
            $this->get('session')->getFlashBag()->add('warning', 'You do not have permissions to this campaign.');
            return $this->redirectToRoute('homepage');
        }


        $mode = 'update';
        $form = $this->createForm('AppBundle\Form\UploadType', array('entity' => $entity, 'file_type' => $entity, 'role' => $this->getUser()->getRoles()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null != $form['upload_mode']->getData()) {
                $mode = $form['upload_mode']->getData();
            } else {
                $logger->error('No mode was selected. defaulted to update');
            }

            $uploadFile = $form['attachment']->getData();

            if (strpos($uploadFile->getClientOriginalName(), '.csv') !== false) {
                $logger->info('File was a .csv, attempting to load');

                $uploadFile->move('temp/', strtolower($entity).'.csv');

                $CSVHelper = new CSVHelper();
                $CSVHelper->processFile('temp/', strtolower($entity).'.csv');
                $templateFields = array('teachers_name', 'grade', 'email');

                if ($CSVHelper->validateHeaders($templateFields)) {
                    $logger->info('Making changes to database');

                    $em = $this->getDoctrine()->getManager();

                    if (strcmp($mode, 'truncate') == 0) {
                        $logger->info('User selected to [truncate] table');

                        $qb = $em->createQueryBuilder();
                        $qb->delete('AppBundle:'.$entity, 's');
                        $query = $qb->getQuery();

                        $query->getResult();

                        $em->flush();

                        $this->addFlash(
                            'info',
                            'The Teachers table has been truncated'
                        );
                    }

                    $logger->info('Uploading Data');
                    $em = $this->getDoctrine()->getManager();
                    $errorMessages = [];
                    $errorMessage;

                    foreach ($CSVHelper->getData() as $i => $item) {
                        $failure = false;
                        unset($errorMessage);
                        $logger->debug('Row ['.$i.'] data: '.print_r($item, true));
                        if (!$failure) {
                            $grade = $this->getDoctrine()->getRepository('AppBundle:Grade')->findOneByName($item['grade']);
                            if (empty($grade)) {
                                $failure = true;
                                $errorMessage = new ValidationHelper(array(
                            'entity' => $entity,
                            'row_index' => ($i + 2),
                            'error_field' => 'grade',
                            'error_field_value' => $item['grade'],
                            'error_message' => 'Could not find grade',
                            'error_level' => ValidationHelper::$level_error, ));
                            }
                        }

                        if (!$failure) {
                            $teacher = $this->getDoctrine()->getRepository('AppBundle:'.$entity)->findOneBy(
                      array('grade' => $grade->getId(), 'teacherName' => $item['teachers_name'])
                      );
                      //Going to perform "Insert" vs "Update"
                        if (empty($teacher)) {
                            $logger->debug($entity.' not found....creating new record');
                            $teacher = new Teacher();
                        } else {
                            $logger->debug($entity.' found....updating existing record');
                            if (strcmp($mode, 'truncate') == 0) {
                                //This means there is a duplicate in the file...
                            $failure = true;
                                $errorMessage = new ValidationHelper(array(
                              'entity' => $entity,
                              'row_index' => ($i + 2),
                              'error_field' => 'teachers_name',
                              'error_field_value' => $item['teachers_name'],
                              'error_message' => 'Duplicate with '.$entity.' #'.$teacher->getId(),
                              'error_level' => ValidationHelper::$level_warning, ));
                            }
                        }
                            if (!$failure) {
                                $teacher->setTeacherName($item['teachers_name']);
                                $teacher->setGrade($grade);
                                $teacher->setEmail($item['email']);

                                $validator = $this->get('validator');
                                $errors = $validator->validate($teacher);

                                if (strcmp($mode, 'validate') !== 0) {
                                    if (count($errors) > 0) {
                                        $errorsString = (string) $errors;
                                        $logger->error('[ROW #'.($i + 2).'] Could not add ['.$entity.']: '.$errorsString);
                                        $this->addFlash(
                                    'danger',
                                    '[ROW #'.($i + 2).'] Could not add ['.$entity.']: '.$errorsString
                                );
                                    } else {
                                        $em->persist($teacher);
                                        $em->flush();
                                        $em->clear();
                                    }
                                } //Otherwise we do Nothing....
                            }
                        }
                        if (isset($errorMessage) && strcmp($mode, 'validate') !== 0) {
                            $this->addFlash(
                                $errorMessage->getErrorLevel(),
                                $errorMessage->printFlashBagMessage()
                            );
                        }

                      //Push Error Message
                      if (isset($errorMessage)) {
                          array_push($errorMessages, $errorMessage->getMap());
                      }
                    }

                    if (strcmp($mode, 'validate') !== 0) {
                        $em->flush();
                        $em->clear();

                        return $this->redirectToRoute(strtolower($entity).'_index');
                    } else {
                        return $this->render('crud/validate.html.twig', array(
                          'error_messages' => $errorMessages,
                          'entity' => $entity,
                      ));
                    }
                } else {
                    $logger->info('file does not have mandatory fields. ['.implode(', ', $templateFields).']');
                    $logger->info('File was not a .csv');
                    $this->addFlash(
                      'danger',
                      'file does not have mandatory fields. ['.implode(', ', $templateFields).']'
                  );
                }
            } else {
                $logger->info('File was not a .csv');
                $this->addFlash(
                    'danger',
                    'File was not a .csv'
                );
            }
        }

        return $this->render('crud/upload.html.twig', array(
            'form' => $form->createView(),
            'entity' => $entity,
            'file_type' => $entity,
        ));
    }

    /**
    *
    * campaignPermissionsCheck takes the campaign that was requested and verifies user has access to it
    *
    * Access is verified by looking at the CampaignUser entity and verifying a record exists
    * for that campaign and user combination
    *
    * @param Campaign $campaign
    *
    * @return boolean
    *
    */
    private function campaignPermissionsCheck(Campaign $campaign){
      $em = $this->getDoctrine()->getManager();

      //CODE TO PROTECT CONTROLLER FROM USERS WHO ARE NOT IN CAMPAIGNUSER TABLE
      //TODO: ADD CODE TO ALLOW ADMINS TO ACCESS
      $query = $em->createQuery('SELECT IDENTITY(cu.campaign) FROM AppBundle:CampaignUser cu where cu.user=?1');
      $query->setParameter(1, $this->get('security.token_storage')->getToken()->getUser());
      $results = array_map('current', $query->getScalarResult());
      if(!in_array($campaign->getId(), $results)){
        return false;
      }
      return true;
    }

}
