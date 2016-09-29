<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use AppBundle\Entity\Grade;
use AppBundle\Entity\Student;
use AppBundle\Utils\CSVHelper;
use AppBundle\Entity\Donation;
use AppBundle\Utils\ValidationHelper;
use DateTime;
use DateTimeZone;

/**
 * Donation controller.
 *
 * @Route("/manage/donation")
 */
class DonationController extends Controller
{
    /**
     * Lists all Donation entities.
     *
     * @Route("/", name="donation_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $entity = 'Donation';
        $em = $this->getDoctrine()->getManager();

        $donations = $em->getRepository('AppBundle:Donation')->findAll();

        return $this->render(strtolower($entity).'/index.html.twig', array(
            'donations' => $donations,
            'entity' => $entity,
        ));
    }

    /**
     * Creates a new Donation entity.
     *
     * @Route("/new", name="donation_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $entity = 'Donation';
        $donation = new Donation();
        $date = new DateTime();
        $dateString = $date->format('Y-m-d').' 00:00:00';
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        $donation->setDonatedAt(new DateTime($date->format('Y-m-d')));

        $donation->setType('manual');

        $form = $this->createForm('AppBundle\Form\DonationType', $donation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($donation);
            $em->flush();

            return $this->redirectToRoute(strtolower($entity).'_index');
        }

        return $this->render('crud/new.html.twig', array(
            'donation' => $donation,
            'form' => $form->createView(),
            'entity' => $entity,
        ));
    }

    /**
     * Finds and displays a Donation entity.
     *
     * @Route("/show/{id}", name="donation_show")
     * @Method("GET")
     */
    public function showAction(Donation $donation)
    {
        $entity = 'Donation';
        $deleteForm = $this->createDeleteForm($donation);

        return $this->render(strtolower($entity).'/show.html.twig', array(
            'donation' => $donation,
            'delete_form' => $deleteForm->createView(),
            'entity' => $entity,
        ));
    }

    /**
     * Displays a form to edit an existing Donation entity.
     *
     * @Route("/edit/{id}", name="donation_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Donation $donation)
    {
        $entity = 'Donation';
        $deleteForm = $this->createDeleteForm($donation);
        $editForm = $this->createForm('AppBundle\Form\DonationType', $donation);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $dateString = $donation->getDonatedAt()->format('Y-m-d').' 00:00:00';
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);

            $donation->setDonatedAt($date);
            $em->persist($donation);
            $em->flush();

            return $this->redirectToRoute(strtolower($entity).'_index', array('id' => $donation->getId()));
        }

        return $this->render('crud/edit.html.twig', array(
            'donation' => $donation,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            'entity' => $entity,
        ));
    }

    /**
     * Deletes a Donation entity.
     *
     * @Route("/delete/{id}", name="donation_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Donation $donation)
    {
        $entity = 'Donation';
        $form = $this->createDeleteForm($donation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($donation);
            $em->flush();
        }

        return $this->redirectToRoute(strtolower($entity).'_index');
    }

    /**
     * Creates a form to delete a Donation entity.
     *
     * @param Donation $donation The Donation entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Donation $donation)
    {
        $entity = 'Donation';

        return $this->createFormBuilder()
            ->setAction($this->generateUrl(strtolower($entity).'_delete', array('id' => $donation->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     * Upload multiple Donation via CSV File.
     *
     * @Route("/upload", name="donation_upload")
     * @Method({"GET", "POST"})
     */
    public function uploadForm(Request $request)
    {
        $logger = $this->get('logger');
        $entity = 'Donation';
        $mode = 'update';

        $fileType = $request->query->get('file_type');
        $logger->debug('file_type: '.$fileType);
        if (strcmp($fileType, 'Offlinedonation') !== 0 && strcmp($fileType, 'Causevoxdonation') !== 0) {
            $this->addFlash(
              'warning',
              'File Type '.$fileType.' not found');

            return $this->redirectToRoute(strtolower($entity).'_index');
        }
        $form = $this->createForm('AppBundle\Form\UploadType', array('entity' => $entity, 'file_type' => $fileType, 'role' => $this->getUser()->getRoles()));
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
                //the offline donation file starts at 1, while causevox, starts at 0.....
                $fileIndexOffset = 0;

                if (strcmp($fileType, 'Offlinedonation') == 0) {
                    $templateFields = array('date', 'grade', 'teachers_name', 'students_name', 'amount');
                    $fileIndexOffset = 1;
                } else {
                    $templateFields = array('donation_page',
                  'fundraiser_first_name',
                  'fundraiser_last_name',
                  'fundraiser_email',
                  'fundraiser_location',
                  'donor_first_name',
                  'donor_last_name',
                  'donor_email',
                  'donor_comment',
                  'anonymous',
                  'line_1',
                  'line_2',
                  'city',
                  'state',
                  'zip_code',
                  'country',
                  'amount',
                  'est_cc_fee',
                  'causevox_fee',
                  'tip',
                  'type',
                  'recurring',
                  'subscribed',
                  'giftaid',
                  'transaction_id',
                  'donated_at',
                  'teachers_name',
                  'students_name', );
                }

                $CSVHelper = new CSVHelper();
                $CSVHelper->setHeaderRowIndex($fileIndexOffset);
                $CSVHelper->processFile('temp/', strtolower($entity).'.csv');

                if (strcmp($fileType, 'Causevoxdonation') == 0) {
                    $CSVHelper->getGradefromTeacherName();
                    $CSVHelper->cleanTeacherNames();
                    $CSVHelper->getFirstNameFromFullName();
                }

                $CSVHelper->cleanAmounts();

                if ($CSVHelper->validateHeaders($templateFields)) {
                    $em = $this->getDoctrine()->getManager();

                    if (strcmp($mode, 'truncate') == 0) {
                        $logger->info('User selected to [truncate] table');

                        $qb = $em->createQueryBuilder();

                        if (strcmp($fileType, 'Offlinedonation') == 0) {
                            $qb->delete('AppBundle:'.$entity, 's');
                            $qb->where("s.source = 'Offlinedonation'");
                            $query = $qb->getQuery();
                            $query->getResult();
                            $em->flush();

                            $this->addFlash(
                              'info',
                              'The Manual donations have been deleted'
                          );
                        } elseif (strcmp($fileType, 'Causevoxdonation') == 0) {
                            $qb->delete('AppBundle:'.$entity, 's');
                            $qb->where("s.source = 'Causevoxdonation'");
                            $query = $qb->getQuery();
                            $query->getResult();
                            $em->flush();

                            $this->addFlash(
                              'info',
                              'Existing Causevox donations were deleted'
                          );
                        }
                    }

                    $logger->info('Uploading Data');
                    $em = $this->getDoctrine()->getManager();
                    $errorMessages = [];
                    $errorMessage;

                    foreach ($CSVHelper->getData() as $i => $item) {
                        $failure = false;
                        unset($errorMessage);

                        if (!$failure) {
                            if (strcmp($fileType, 'Causevoxdonation') == 0 && strcmp($item['type'], 'manual') == 0) {
                                $failure = true;
                              //We do not process "manual" causevox donations as they are offline donations we collect elsewhere
                            } elseif (strcmp($fileType, 'Offlinedonation') == 0) {
                                $item['type'] = 'manual';
                            }
                        }

                        if (!$failure) {
                            if (is_null($item['amount']) || !isset($item['amount']) || empty($item['amount']) || strcmp($item['amount'], '') == 0) {
                                $failure = true;
                              //We do not notify if amount is empty.....we just ignore it.
                            }
                        }
                        if (!$failure) {
                            if (strcmp($fileType, 'Causevoxdonation') == 0) {
                                if (!isset($item['donated_at']) || empty($item['donated_at']) || strcmp('none', $item['donated_at']) == 0) {
                                    $failure = true;
                                    $errorMessage = new ValidationHelper(array(
                                  'entity' => $entity,
                                  'row_index' => ($i + 2 + $fileIndexOffset),
                                  'error_field' => 'date',
                                  'error_field_value' => $item['date'],
                                  'error_message' => 'Date cannot be null',
                                  'error_level' => ValidationHelper::$level_error, ));
                                }
                            } else {
                                if (!isset($item['date']) || empty($item['date']) || strcmp('none', $item['date']) == 0) {
                                    $failure = true;
                                    $errorMessage = new ValidationHelper(array(
                                  'entity' => $entity,
                                  'row_index' => ($i + 2 + $fileIndexOffset),
                                  'error_field' => 'date',
                                  'error_field_value' => $item['date'],
                                  'error_message' => 'Date cannot be null',
                                  'error_level' => ValidationHelper::$level_error, ));
                                }
                            }
                        }

                        if (!$failure) {
                            $grade = $this->getDoctrine()->getRepository('AppBundle:Grade')->findOneByName($item['grade']);
                            if (empty($grade)) {
                                $failure = true;
                                $errorMessage = new ValidationHelper(array(
                                'entity' => $entity,
                                'row_index' => ($i + 2 + $fileIndexOffset),
                                'error_field' => 'grade',
                                'error_field_value' => $item['grade'],
                                'error_message' => 'Could not find grade',
                                'error_level' => ValidationHelper::$level_error, ));
                            }
                        }

                        if (!$failure) {
                            $teacher = $this->getDoctrine()->getRepository('AppBundle:Teacher')->findOneByTeacherName($item['teachers_name']);
                            if (empty($teacher)) {
                                $failure = true;
                                $errorMessage = new ValidationHelper(array(
                                'entity' => $entity,
                                'row_index' => ($i + 2 + $fileIndexOffset),
                                'error_field' => 'teachers_name',
                                'error_field_value' => $item['teachers_name'],
                                'error_message' => 'Could not find teacher',
                                'error_level' => ValidationHelper::$level_error, ));
                            }
                        }

                        //Here is our find student logic. We try a lot of different methods to try and find it....
                        if (!$failure) {

                          //NOW WE ARE TRYING TO USE THE ASSOCIATED DONATION PAGE
                          if (isset($item['donation_page'])) {
                              //GETTING URL STRING TO FIND FROM TABLE
                              $urlString = substr($item['donation_page'], 1, strlen($item['donation_page'])); // Chopping off the '/'
                              //$lastinitial = substr($lastname,0,1).'.';
                              $queryString = sprintf("SELECT IDENTITY(u.student, 'id') as student_id FROM AppBundle:Causevoxfundraiser u WHERE u.url = '%s'", $urlString);
                              $logger->debug("QueryString: ".$queryString);
                              $result = $em->createQuery($queryString)->getResult();
                              $logger->debug("Found Causevoxfundraiser: ".print_r($result, true));
                              if (!empty($result)) {
                                  $studentId = $result[0]['student_id'];
                                  $logger->debug('Row ['.($i + 2 + $fileIndexOffset).'] - Found student "'.$item['students_name'].'" [#'.$studentId.'] using associated Causevoxfundraiser URL "'.$item['donation_page'].'"');
                              }
                          }

                            if (!isset($studentId)) {
                                $queryString = sprintf("SELECT u.id FROM AppBundle:Student u WHERE u.teacher <= '%s' AND u.name = '%s'", $teacher->getId(), $item['students_name']);
                                $result = $em->createQuery($queryString)->getResult();

                                if (!empty($result)) {
                                    $studentId = $result[0]['id'];
                                    $logger->debug('Row ['.($i + 2 + $fileIndexOffset).'] - Found student "'.$item['students_name'].'" [#'.$studentId.'] using provided name');
                                }
                            }
                            if (!isset($studentId)) {
                                $queryString = sprintf("SELECT u.id FROM AppBundle:Student u WHERE u.teacher <= '%s' AND u.name = '%s'", $teacher->getId(), $item['students_first_name']);
                                $result = $em->createQuery($queryString)->getResult();
                                if (!empty($result)) {
                                    $studentId = $result[0]['id'];
                                    $logger->debug('Row ['.($i + 2 + $fileIndexOffset).'] - Found student "'.$item['students_name'].'" [#'.$studentId.'] using first name fuzzy match "'.$item['students_first_name'].'"');
                                }
                            }

                            if (!isset($studentId)) {
                                $queryString = sprintf("SELECT u.id FROM AppBundle:Student u WHERE u.teacher <= '%s' AND u.name = '%s'", $teacher->getId(), $item['students_name_with_initial']);
                                $result = $em->createQuery($queryString)->getResult();

                                if (!empty($result)) {
                                    $studentId = $result[0]['id'];
                                    $logger->debug('Row ['.($i + 2 + $fileIndexOffset).'] - Found student "'.$item['students_name'].'" [#'.$studentId.'] using first name + last initial fuzzy match "'.$item['students_name_with_initial'].'"');
                                }
                            }

                          //IF ALL ELSE FAILES, ITS A FAILURE
                          if (!isset($studentId)) {
                              $failure = true;
                              $errorMessage = new ValidationHelper(array(
                                  'entity' => $entity,
                                  'row_index' => ($i + 2 + $fileIndexOffset),
                                  'error_field' => 'students_name, teacher, grade',
                                  'error_field_value' => $item['students_name'].', '.$item['teachers_name'].', '.$item['grade'],
                                  'error_message' => 'Could not find student',
                                  'error_level' => ValidationHelper::$level_error, ));
                          }
                        } //END STUDENT FIND LOGIC

                        if (!$failure) {
                            $student = $em->find('AppBundle:Student', $studentId);
                          //Example: 2016-08-25 16:35:54
                          //Causevox donations are given to us as UTC...which we need to convert back to EST
                          if (strcmp($fileType, 'Causevoxdonation') == 0) {
                              $tempDate = new DateTime($item['donated_at'],  new DateTimeZone('UTC'));
                              $tempDate->setTimezone(new DateTimeZone('America/New_York'));
                              $dateString = $tempDate->format('Y-m-d').' 00:00:00';
                              $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString,  new DateTimeZone('America/New_York'));
                          } else {
                              $tempDate = new DateTime($item['date']);
                              $dateString = $tempDate->format('Y-m-d').' 00:00:00';
                              $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
                          }

                            if (strcmp($fileType, 'Causevoxdonation') == 0) {
                                $donation = $this->getDoctrine()->getRepository('AppBundle:'.$entity)->findOneBy(
                                array('student' => $student, 'donatedAt' => $date, 'transactionId' => $item['transaction_id'], 'source' => $fileType));
                            } elseif (strcmp($fileType, 'Offlinedonation') == 0) {
                                $donation = $this->getDoctrine()->getRepository('AppBundle:'.$entity)->findOneBy(
                                array('student' => $student, 'donatedAt' => $date, 'source' => $fileType));
                            }

                          //Going to perform "Insert" vs "Update"
                          if (empty($donation)) {
                              $logger->debug($entity.' not found....creating new record');
                              $donation = new Donation();
                          } else {
                              $logger->debug($entity.' found....updating.');
                              $failure = true;
                              /*
                              $errorMessage = new ValidationHelper(array(
                                'entity' => $entity,
                                'row_index' => ($i + 2 + $fileIndexOffset),
                                'error_field' => 'N/A',
                                'error_field_value' => 'N/A',
                                'error_message' => 'A donation for this student and date already exists #'.$donation->getId(),
                                'error_level' => ValidationHelper::$level_error, ));
                              */
                          }
                        }
                        if (!$failure) {
                            if (strcmp($fileType, 'Causevoxdonation') == 0) {
                                //a lot more information is collected from causevox....
                                $donation->setTip($item['tip']);
                                $donation->setEstimatedCcFee($item['est_cc_fee']);
                                $donation->setCausevoxFee($item['causevox_fee']);

                                $donation->setDonorFirstName($item['donor_first_name']);
                                $donation->setDonorLastName($item['donor_last_name']);
                                $donation->setDonorEmail($item['donor_email']);
                                $donation->setDonorComment($item['donor_comment']);
                                $donation->setDonationPage($item['donation_page']);
                                $donation->setTransactionId($item['transaction_id']);
                            }

                            $donation->setSource($fileType);
                            $donation->setType($item['type']);
                            $donation->setAmount($item['amount']);
                            $donation->setDonatedAt($date);
                            $donation->setStudent($student);

                            $validator = $this->get('validator');
                            $errors = $validator->validate($donation);

                            if (strcmp($mode, 'validate') !== 0) {
                                if (count($errors) > 0) {
                                    $errorsString = (string) $errors;
                                    $logger->error('[ROW #'.($i + 2 + $fileIndexOffset).'] Could not add ['.$entity.']: '.$errorsString);
                                    $this->addFlash(
                                        'danger',
                                        '[ROW #'.($i + 2 + $fileIndexOffset).'] Could not add ['.$entity.']: '.$errorsString
                                    );
                                } else {
                                    $em->persist($donation);
                                    $em->flush();
                                    $em->clear();
                                }
                            } //Otherwise we do Nothing....
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
                          'file_type' => $fileType,
                      ));
                    }
                } else {
                    $logger->info('file does not have mandatory fields. ['.implode(', ', $templateFields).']. Please validate it was downloaded from the "FUNRUN LEDGER"');
                    $logger->info('File was not a .csv');
                    $this->addFlash(
                        'danger',
                        'file does not have mandatory fields. ['.implode(', ', $templateFields).']. Please validate you are matching the '.$fileType.' file format'
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
      ));
    }
}